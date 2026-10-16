import {
  getBonusAnswer,
  getDailyAnswer,
  getPuzzleNumber,
  msUntilNextPuzzle,
  normalizeWordList,
  scoreGuess,
  toDateKey,
  WORD_LENGTH,
} from "./word-logic.js";

const KEY_ROWS = ["QWERTYUIOP", "ASDFGHJKL", ["ENTER", ..."ZXCVBNM", "BACKSPACE"]];
const FLIP_DURATION_MS = 420;
const FLIP_STAGGER_MS = 120;
const BOUNCE_STAGGER_MS = 100;
const TOAST_DURATION_MS = 1500;
const WIN_MESSAGES = ["Genius", "Magnificent", "Impressive", "Splendid", "Great", "Phew"];
const DAILY_ROUND = 1;

let validGuessesPromise = null;

function loadValidGuesses() {
  validGuessesPromise ??= import("./valid-guesses.js")
    .then((module) => new Set(module.VALID_GUESSES))
    .catch((error) => {
      console.warn("Ash Wordle: guess list failed to load; accepting any five-letter guess.", error);
      validGuessesPromise = null;
      return null;
    });
  return validGuessesPromise;
}

function formatCountdown(ms) {
  const totalSeconds = Math.max(0, Math.floor(ms / 1000));
  const hours = Math.floor(totalSeconds / 3600);
  const minutes = Math.floor((totalSeconds % 3600) / 60);
  const seconds = totalSeconds % 60;
  return [hours, minutes, seconds].map((part) => String(part).padStart(2, "0")).join(":");
}

export class AshWordleElement extends HTMLElement {
  constructor() {
    super();
    this._words = [];
    this._solutionOverride = null;
    this._persistence = null;
    this._validGuesses = null;
    this._solution = "";
    this._dailySolution = "";
    this._round = DAILY_ROUND;
    this._bonus = null;
    this._bonusIndex = -1;
    this._statsView = null;
    this._dateKey = "";
    this._puzzleNumber = 0;
    this._maxAttempts = 6;
    this._guesses = [];
    this._currentGuess = "";
    this._message = "";
    this._finished = null;
    this._loading = false;
    this._loadToken = 0;
    this._stats = null;
    this._isRevealing = false;
    this._revealingRow = -1;
    this._pendingOutcome = null;
    this._revealTimer = null;
    this._messageTimer = null;
    this._countdownTimer = null;
    this._popIndex = -1;
    this._shakeRow = -1;
    this._bounceRow = -1;

    this.attachShadow({ mode: "open" });
    this.shadowRoot.addEventListener("click", (event) => this._handleClick(event));
    this.addEventListener("keydown", (event) => this._handleKeydown(event));
  }

  connectedCallback() {
    this.tabIndex = 0;
    this.setAttribute("role", "region");
    this.setAttribute("aria-label", "Ash Wordle game");
    this._startGame();
  }

  disconnectedCallback() {
    this._loadToken += 1;
    this._clearTimers();
  }

  set words(value) {
    this._words = normalizeWordList(value);
    if (this.isConnected) this._startGame();
  }

  get words() {
    return [...this._words];
  }

  // Fixes the answer for testing. Games with an override are never saved.
  set solution(value) {
    const normalized = normalizeWordList([value]);
    this._solutionOverride = normalized[0] ?? null;
    if (this.isConnected) this._startGame();
  }

  get solution() {
    return this._solution;
  }

  // An object with load(dateKey) and save(dateKey, { round, solution, guesses }); see wordle-api.js.
  set persistence(value) {
    this._persistence = value ?? null;
    if (this.isConnected) this._startGame();
  }

  get persistence() {
    return this._persistence;
  }

  set maxAttempts(value) {
    const parsed = Number.parseInt(value, 10);
    this._maxAttempts = Number.isFinite(parsed) ? Math.max(1, parsed) : 6;
    if (this.isConnected) this._render();
  }

  get maxAttempts() {
    return this._maxAttempts;
  }

  reset() {
    this._startGame();
  }

  async _startGame() {
    const token = ++this._loadToken;
    const today = new Date();
    this._dateKey = toDateKey(today);
    this._puzzleNumber = getPuzzleNumber(today);
    this._solution = this._solutionOverride ?? getDailyAnswer(this._words, today) ?? "";
    this._dailySolution = this._solution;
    this._round = DAILY_ROUND;
    this._stats = null;
    this._bonus = null;
    this._bonusIndex = -1;
    this._resetRound();
    if (!this._solution) return;

    this._loading = true;
    this._render();

    const [saved, validGuesses] = await Promise.all([this._loadSavedGame(), loadValidGuesses()]);
    if (token !== this._loadToken) return;

    this._validGuesses = validGuesses;
    this._loading = false;
    if (saved) this._applySavedDay(saved);
    this._render();
    if (this._finished !== null) this._startCountdown();
  }

  // "Another?" starts a bonus round: a fresh word the same day, saved as round 2, 3, and so on.
  _startNextWord() {
    if (this._finished === null || this._isRevealing) return;

    const bonusIndex = Math.max(this._bonus?.started ?? 0, this._bonusIndex + 1);
    const solution = getBonusAnswer(this._words, bonusIndex, [this._dailySolution]);
    if (!solution) return;

    this._round = Math.max(this._round, this._bonus?.lastRound ?? DAILY_ROUND) + 1;
    this._bonusIndex = bonusIndex;
    this._solution = solution;
    this._resetRound();
    this.focus({ preventScroll: true });
  }

  _resetRound() {
    this._clearTimers();
    this._guesses = [];
    this._currentGuess = "";
    this._finished = null;
    this._loading = false;
    this._isRevealing = false;
    this._revealingRow = -1;
    this._pendingOutcome = null;
    this._statsView = null;
    this._message = this._solution ? "" : "Add a word list to start.";
    this._render();
  }

  _clearTimers() {
    clearTimeout(this._revealTimer);
    clearTimeout(this._messageTimer);
    clearInterval(this._countdownTimer);
    this._revealTimer = null;
    this._messageTimer = null;
    this._countdownTimer = null;
  }

  _canPersist() {
    return Boolean(this._persistence) && this._solutionOverride === null;
  }

  async _loadSavedGame() {
    if (!this._canPersist()) return null;

    try {
      return await this._persistence.load(this._dateKey);
    } catch (error) {
      console.warn("Ash Wordle: could not load saved progress.", error);
      return null;
    }
  }

  async _saveProgress() {
    if (!this._canPersist()) return;

    const token = this._loadToken;
    const round = this._round;
    try {
      const result = await this._persistence.save(this._dateKey, {
        round,
        solution: this._solution,
        guesses: [...this._guesses],
      });
      if (token !== this._loadToken) return;

      this._applyServerStats(result);
      if (result.conflict) {
        if (round === this._round) this._adoptServerGame(result.game);
        return;
      }

      this._renderResultsInPlace();
    } catch (error) {
      console.warn("Ash Wordle: could not save progress.", error);
    }
  }

  // Reopening resumes an unfinished bonus round; otherwise it shows the daily word.
  _applySavedDay(saved) {
    this._applyServerStats(saved);
    if (saved.game) this._dailySolution = saved.game.solution;

    const bonusGame = saved.bonus?.game;
    if (bonusGame?.status === "in_progress") {
      this._bonusIndex = Math.max(0, (saved.bonus.started ?? 1) - 1);
      this._applyGame(bonusGame);
    } else {
      this._applyGame(saved.game);
    }
  }

  _applyServerStats({ stats, bonus }) {
    if (stats) this._stats = stats;
    if (bonus) this._bonus = bonus;
  }

  _applyGame(game) {
    if (!game) return;

    this._round = game.round ?? DAILY_ROUND;
    this._solution = game.solution;
    this._guesses = game.guesses.slice(0, this._maxAttempts);
    this._finished = game.status === "won" || game.status === "lost" ? game.status : null;
    this._message = this._finished === "lost" ? `The word was ${this._solution}.` : "";
  }

  // This word was already moved on elsewhere (another tab or device); show that instead.
  _adoptServerGame(game) {
    clearTimeout(this._revealTimer);
    this._isRevealing = false;
    this._revealingRow = -1;
    this._pendingOutcome = null;
    this._currentGuess = "";
    this._applyGame(game);
    this._render();
    if (this._finished !== null) this._startCountdown();
  }

  _isPlayable() {
    return Boolean(this._solution) && !this._loading && this._finished === null && !this._isRevealing;
  }

  _isValidGuess(guess) {
    return !this._validGuesses
      || this._validGuesses.has(guess)
      || this._words.includes(guess)
      || guess === this._solution;
  }

  _handleClick(event) {
    if (event.target.closest("button[data-action='next-word']")) {
      this._startNextWord();
      return;
    }

    const statsTab = event.target.closest("button[data-stats-view]");
    if (statsTab) {
      this._statsView = statsTab.dataset.statsView;
      this._renderResultsInPlace();
      return;
    }

    const keyButton = event.target.closest("button[data-key]");
    if (!keyButton) return;

    this._handleKey(keyButton.dataset.key);
    this.focus({ preventScroll: true });
  }

  _handleKeydown(event) {
    if (event.altKey || event.ctrlKey || event.metaKey) return;
    if (!this._solution || this._finished !== null) return;

    const key = event.key.toUpperCase();
    if (key !== "ENTER" && key !== "BACKSPACE" && !/^[A-Z]$/.test(key)) return;

    event.preventDefault();
    this._handleKey(key);
  }

  _handleKey(key) {
    if (!this._isPlayable()) return;

    if (key === "ENTER") {
      this._submitGuess();
    } else if (key === "BACKSPACE") {
      if (!this._currentGuess) return;
      this._currentGuess = this._currentGuess.slice(0, -1);
      this._render();
    } else if (/^[A-Z]$/.test(key) && this._currentGuess.length < WORD_LENGTH) {
      this._currentGuess += key;
      this._popIndex = this._currentGuess.length - 1;
      this._render();
    }
  }

  _submitGuess() {
    const guess = this._currentGuess;
    if (guess.length !== WORD_LENGTH) {
      this._rejectGuess("Not enough letters");
      return;
    }
    if (!this._isValidGuess(guess)) {
      this._rejectGuess("Not in word list");
      return;
    }

    this._guesses.push(guess);
    this._currentGuess = "";
    this._isRevealing = true;
    this._revealingRow = this._guesses.length - 1;

    if (guess === this._solution) {
      this._pendingOutcome = "won";
    } else if (this._guesses.length >= this._maxAttempts) {
      this._pendingOutcome = "lost";
    } else {
      this._pendingOutcome = null;
    }

    clearTimeout(this._messageTimer);
    this._message = "";
    this._render();
    this._scheduleRevealCompletion();
    this._saveProgress();
  }

  _rejectGuess(message) {
    this._shakeRow = this._guesses.length;
    this._render();
    this._showToast(message);
  }

  _showToast(message) {
    clearTimeout(this._messageTimer);
    this._message = message;
    this._renderMessage();
    this._messageTimer = setTimeout(() => {
      this._message = "";
      this._renderMessage();
    }, TOAST_DURATION_MS);
  }

  _scheduleRevealCompletion() {
    const animationDuration = window.matchMedia("(prefers-reduced-motion: reduce)").matches
      ? 0
      : FLIP_DURATION_MS + (WORD_LENGTH - 1) * FLIP_STAGGER_MS;

    this._revealTimer = setTimeout(() => {
      this._isRevealing = false;
      this._revealingRow = -1;
      this._finished = this._pendingOutcome;
      this._pendingOutcome = null;

      if (this._finished === "won") {
        this._bounceRow = this._guesses.length - 1;
        this._message = WIN_MESSAGES[Math.min(this._guesses.length, WIN_MESSAGES.length) - 1];
      } else if (this._finished === "lost") {
        this._message = `The word was ${this._solution}.`;
      }

      this._render();
      if (this._finished !== null) {
        this._startCountdown();
        this._announceCompletion(this._finished === "won");
      }
      this.focus({ preventScroll: true });
    }, animationDuration);
  }

  _startCountdown() {
    clearInterval(this._countdownTimer);

    const tick = () => {
      if (toDateKey() !== this._dateKey) {
        this._startGame();
        return;
      }

      const countdown = this.shadowRoot.querySelector("[data-countdown]");
      if (countdown) countdown.textContent = formatCountdown(msUntilNextPuzzle());
    };

    tick();
    this._countdownTimer = setInterval(tick, 1000);
  }

  _announceCompletion(won) {
    this.dispatchEvent(new CustomEvent("ash-wordle-complete", {
      bubbles: true,
      composed: true,
      detail: {
        won,
        date: this._dateKey,
        round: this._round,
        solution: this._solution,
        attempts: this._guesses.length,
        guesses: [...this._guesses],
      },
    }));
  }

  _keyStatuses() {
    const priority = { absent: 1, present: 2, correct: 3 };
    const statuses = new Map();

    for (const guess of this._guesses) {
      const feedback = scoreGuess(guess, this._solution);
      [...guess].forEach((letter, index) => {
        const current = statuses.get(letter);
        if (!current || priority[feedback[index]] > priority[current]) {
          statuses.set(letter, feedback[index]);
        }
      });
    }

    return statuses;
  }

  _renderBoard() {
    const rows = [];

    for (let rowIndex = 0; rowIndex < this._maxAttempts; rowIndex += 1) {
      const isSubmitted = rowIndex < this._guesses.length;
      const isCurrentRow = rowIndex === this._guesses.length;
      const guess = isSubmitted ? this._guesses[rowIndex] : isCurrentRow ? this._currentGuess : "";
      const feedback = isSubmitted ? scoreGuess(guess, this._solution) : [];
      const isRevealingRow = this._isRevealing && rowIndex === this._revealingRow;
      const isBouncingRow = rowIndex === this._bounceRow;

      const cells = Array.from({ length: WORD_LENGTH }, (_, index) => {
        const letter = guess[index] ?? "";
        const state = feedback[index] ?? "";
        const classes = [
          "tile",
          state,
          isRevealingRow ? "flip-reveal" : "",
          isBouncingRow ? "bounce" : "",
          isCurrentRow && index === this._popIndex ? "pop" : "",
        ].filter(Boolean).join(" ");
        const label = letter ? `Letter ${letter}${state ? `, ${state}` : ""}` : "Empty";
        return `<span class="${classes}" style="--tile-index: ${index}" aria-label="${label}">${letter}</span>`;
      }).join("");

      const shake = rowIndex === this._shakeRow ? " shake" : "";
      rows.push(`<div class="board-row${shake}" aria-label="Guess ${rowIndex + 1}">${cells}</div>`);
    }

    return rows.join("");
  }

  _renderKeyboard() {
    const statuses = this._keyStatuses();

    return KEY_ROWS.map((row) => {
      const keys = typeof row === "string" ? [...row] : row;
      const buttons = keys.map((key) => {
        const wide = key.length > 1 ? " wide" : "";
        const state = statuses.get(key) ?? "";
        const label = key === "BACKSPACE" ? "Delete" : key === "ENTER" ? "Enter" : key;
        const text = key === "BACKSPACE" ? "Del" : label;
        return `<button type="button" class="key${wide} ${state}" data-key="${key}" aria-label="${label}">${text}</button>`;
      }).join("");

      return `<div class="keyboard-row">${buttons}</div>`;
    }).join("");
  }

  _renderResults() {
    const footer = `
      <div class="results-footer">
        <div class="next-puzzle">
          <span>Next daily word in</span>
          <strong data-countdown>${formatCountdown(msUntilNextPuzzle())}</strong>
        </div>
        <button type="button" class="next-word" data-action="next-word">Another?</button>
      </div>
    `;
    // Opens on the stats for the word just played; the Daily/Bonus tabs switch between them.
    const playedBonus = this._round > DAILY_ROUND;
    const view = this._statsView ?? (playedBonus ? "bonus" : "daily");
    const isBonus = view === "bonus";
    const stats = isBonus ? this._bonus?.stats : this._stats;
    if (!stats) return `<section class="results" data-results>${footer}</section>`;

    const heading = this._stats && this._bonus?.stats
      ? `
        <div class="stats-tabs" role="tablist" aria-label="Statistics">
          ${[["daily", "Daily"], ["bonus", "Bonus"]].map(([value, label]) => `
            <button type="button" role="tab" class="stats-tab" data-stats-view="${value}" aria-selected="${value === view}">${label}</button>
          `).join("")}
        </div>
      `
      : `<h3>${isBonus ? "Bonus Statistics" : "Statistics"}</h3>`;

    const figureList = isBonus
      ? [[stats.played, "Bonus Played"], [stats.winPercentage, "Win %"]]
      : [
        [stats.played, "Played"],
        [stats.winPercentage, "Win %"],
        [stats.currentStreak, "Current Streak"],
        [stats.maxStreak, "Max Streak"],
      ];
    const figures = figureList.map(([value, label]) => `<div class="figure"><strong>${Number(value) || 0}</strong><span>${label}</span></div>`).join("");

    const distribution = Array.isArray(stats.guessDistribution) ? stats.guessDistribution.map(Number) : [];
    const mostCommon = Math.max(1, ...distribution);
    const todaysAttempts = this._finished === "won" && isBonus === playedBonus ? this._guesses.length : 0;
    const bars = distribution.map((count, index) => {
      const attempts = index + 1;
      const width = Math.max(8, Math.round((count / mostCommon) * 100));
      const current = attempts === todaysAttempts ? " current" : "";
      return `
        <li>
          <span class="dist-label">${attempts}</span>
          <span class="dist-track"><span class="dist-bar${current}" style="width: ${width}%">${count}</span></span>
        </li>
      `;
    }).join("");

    return `
      <section class="results" data-results aria-label="Statistics">
        ${heading}
        <div class="figures" style="--figure-count: ${figureList.length}">${figures}</div>
        <h3>Guess Distribution</h3>
        <ol class="distribution">${bars}</ol>
        ${footer}
      </section>
    `;
  }

  // Stats can arrive mid-animation, so swap only that section instead of re-rendering the board.
  _renderResultsInPlace() {
    const results = this.shadowRoot.querySelector("[data-results]");
    if (results) results.outerHTML = this._renderResults();
  }

  _renderMessage() {
    const message = this.shadowRoot.querySelector(".message");
    if (message) message.textContent = this._message;
  }

  _render() {
    const isReady = Boolean(this._solution) && !this._loading;
    const puzzleLabel = this._puzzleNumber > 0 ? `#${this._puzzleNumber} · ` : "";
    const bonusLabel = this._round > DAILY_ROUND ? `Bonus ${this._round - DAILY_ROUND} · ` : "";
    const progress = `${puzzleLabel}${bonusLabel}${this._guesses.length} / ${this._maxAttempts}`;
    const message = this._loading ? "Loading…" : this._message;
    const controls = this._finished !== null
      ? this._renderResults()
      : `<div class="keyboard" aria-label="On-screen keyboard">${this._renderKeyboard()}</div>`;

    this.shadowRoot.innerHTML = `
      <style>
        :host {
          display: block;
          width: 100%;
          max-width: 390px;
          color: #242923;
          font-family: system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
          outline: none;
        }

        .topline {
          display: flex;
          align-items: baseline;
          justify-content: space-between;
          gap: 16px;
          padding: 0 0 14px;
          border-bottom: 1px solid #dedfd8;
        }
        h2 {
          margin: 0;
          font-family: Georgia, "Times New Roman", serif;
          font-size: 22px;
          font-weight: 400;
        }
        .progress { color: #72766f; font-size: 12px; white-space: nowrap; }
        .board {
          display: grid;
          gap: 6px;
          width: min(100%, 328px);
          margin: 22px auto 18px;
        }
        .board-row {
          display: grid;
          grid-template-columns: repeat(${WORD_LENGTH}, minmax(0, 1fr));
          gap: 6px;
        }
        .tile {
          display: grid;
          aspect-ratio: 1;
          place-items: center;
          border: 1px solid #c9ccc5;
          font-size: 22px;
          font-weight: 650;
          line-height: 1;
          transform-origin: center;
          backface-visibility: hidden;
        }
        .tile:not(:empty) { border-color: #8e948a; }
        .correct, .present, .absent {
          border-color: transparent !important;
          color: #fff;
        }
        .correct { background: #4d7959; }
        .present { background: #b9973e; }
        .absent { background: #777c75; }
        .key.correct { background: #4d7959; }
        .key.present { background: #b9973e; }
        .key.absent { background: #777c75; }
        .flip-reveal { animation: flip-reveal ${FLIP_DURATION_MS}ms ease-in-out both; animation-delay: calc(var(--tile-index) * ${FLIP_STAGGER_MS}ms); }
        .flip-reveal.correct { --reveal-color: #4d7959; }
        .flip-reveal.present { --reveal-color: #b9973e; }
        .flip-reveal.absent { --reveal-color: #777c75; }
        @keyframes flip-reveal {
          0%, 44% { transform: rotateX(0); background: #f5f4ef; color: #242923; }
          50% { transform: rotateX(90deg); background: #f5f4ef; color: transparent; }
          51% { transform: rotateX(90deg); background: var(--reveal-color); color: #fff; }
          100% { transform: rotateX(0); background: var(--reveal-color); color: #fff; }
        }
        .pop { animation: pop 100ms ease-out; }
        @keyframes pop {
          from { transform: scale(0.8); opacity: 0; }
          40% { transform: scale(1.1); opacity: 1; }
        }
        .shake { animation: shake 600ms; }
        @keyframes shake {
          10%, 90% { transform: translateX(-1px); }
          20%, 80% { transform: translateX(2px); }
          30%, 50%, 70% { transform: translateX(-4px); }
          40%, 60% { transform: translateX(4px); }
        }
        .bounce { animation: bounce 1000ms ease; animation-delay: calc(var(--tile-index) * ${BOUNCE_STAGGER_MS}ms); }
        @keyframes bounce {
          0%, 20% { transform: translateY(0); }
          40% { transform: translateY(-30px); }
          50% { transform: translateY(5px); }
          60% { transform: translateY(-15px); }
          80% { transform: translateY(2px); }
          100% { transform: translateY(0); }
        }
        .message {
          min-height: 22px;
          margin: 0 0 10px;
          color: #4b514a;
          font-size: 13px;
          font-weight: 600;
          text-align: center;
        }
        .keyboard { display: grid; gap: 7px; }
        .keyboard-row { display: flex; justify-content: center; gap: 4px; }
        .key {
          min-width: 0;
          width: 8.8%;
          height: 44px;
          padding: 0;
          border: 0;
          border-radius: 3px;
          background: #dfe1db;
          color: #242923;
          font: 600 12px/1 system-ui, sans-serif;
          cursor: pointer;
          touch-action: manipulation;
        }
        .key.wide { width: 13%; font-size: 10px; }
        .key.correct, .key.present, .key.absent { color: #fff; }
        .key:focus-visible { outline: none; }
        .results { padding-top: 14px; border-top: 1px solid #dedfd8; }
        h3 {
          margin: 0 0 10px;
          color: #4b514a;
          font-size: 11px;
          font-weight: 700;
          letter-spacing: 0.08em;
          text-align: center;
          text-transform: uppercase;
        }
        .stats-tabs {
          display: flex;
          width: fit-content;
          margin: 0 auto 14px;
          border: 1px solid #c9ccc5;
          border-radius: 3px;
          overflow: hidden;
        }
        .stats-tab {
          padding: 7px 18px;
          border: 0;
          background: transparent;
          color: #4b514a;
          font: 700 11px/1.2 system-ui, sans-serif;
          letter-spacing: 0.08em;
          text-transform: uppercase;
          cursor: pointer;
        }
        .stats-tab + .stats-tab { border-left: 1px solid #c9ccc5; }
        .stats-tab[aria-selected="true"] { background: #4d7959; color: #fff; }
        .stats-tab:focus-visible { outline: none; }
        .figures {
          display: grid;
          grid-template-columns: repeat(var(--figure-count, 4), minmax(0, 1fr));
          gap: 4px;
          margin-bottom: 18px;
          text-align: center;
        }
        .figure strong {
          display: block;
          font-family: Georgia, "Times New Roman", serif;
          font-size: 28px;
          font-weight: 400;
          line-height: 1.1;
        }
        .figure span { display: block; color: #72766f; font-size: 11px; }
        .distribution { display: grid; gap: 4px; margin: 0 0 18px; padding: 0; list-style: none; }
        .distribution li { display: flex; align-items: center; gap: 6px; font-size: 12px; }
        .dist-label { width: 10px; font-weight: 600; text-align: center; }
        .dist-track { flex: 1; }
        .dist-bar {
          display: block;
          box-sizing: border-box;
          min-width: fit-content;
          padding: 2px 6px;
          background: #777c75;
          color: #fff;
          font-weight: 700;
          text-align: right;
        }
        .dist-bar.current { background: #4d7959; }
        .results-footer {
          display: flex;
          align-items: center;
          justify-content: space-between;
          gap: 16px;
        }
        .next-puzzle span {
          display: block;
          color: #72766f;
          font-size: 11px;
          letter-spacing: 0.06em;
          text-transform: uppercase;
        }
        .next-puzzle strong {
          font-size: 24px;
          font-variant-numeric: tabular-nums;
          font-weight: 400;
        }
        .next-word {
          padding: 10px 22px;
          border: 1px solid #4d7959;
          border-radius: 3px;
          background: #4d7959;
          color: #fff;
          font: 600 13px/1.2 system-ui, sans-serif;
          cursor: pointer;
        }
        .next-word:focus-visible { outline: none; }
        :host(:focus-visible) { outline: none; }
        @media (max-width: 360px) {
          .key { height: 40px; }
          .keyboard-row { gap: 3px; }
        }
        @media (prefers-reduced-motion: reduce) {
          .flip-reveal, .pop, .shake, .bounce { animation: none; }
        }
      </style>
      <section class="game" style="--word-length: ${WORD_LENGTH}">
        <header class="topline">
          <h2>Ash Wordle</h2>
          <span class="progress">${progress}</span>
        </header>
        <div class="board" aria-label="Wordle board">${this._renderBoard()}</div>
        <p class="message" role="status" aria-live="polite">${message}</p>
        ${isReady ? controls : ""}
      </section>
    `;

    this._popIndex = -1;
    this._shakeRow = -1;
    this._bounceRow = -1;
  }
}

export function defineAshWordleElement() {
  if (typeof customElements === "undefined") return;
  if (!customElements.get("ash-wordle")) {
    customElements.define("ash-wordle", AshWordleElement);
  }
}
