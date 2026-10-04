import { getDailyAnswer, normalizeWordList, scoreGuess } from "./word-logic.js";

const KEY_ROWS = ["QWERTYUIOP", "ASDFGHJKL", ["ENTER", ..."ZXCVBNM", "BACKSPACE"]];
const FLIP_DURATION_MS = 420;
const FLIP_STAGGER_MS = 120;

export class AshWordleElement extends HTMLElement {
  constructor() {
    super();
    this._words = [];
    this._solutionOverride = null;
    this._solution = "";
    this._wordIndex = 0;
    this._maxAttempts = 6;
    this._guesses = [];
    this._currentGuess = "";
    this._message = "";
    this._finished = null;
    this._wordLength = 5;
    this._isRevealing = false;
    this._revealingRow = -1;
    this._pendingOutcome = null;
    this._revealTimer = null;

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

  set words(value) {
    this._words = normalizeWordList(value);
    if (this.isConnected) this._startGame();
  }

  get words() {
    return [...this._words];
  }

  set solution(value) {
    const normalized = normalizeWordList([value]);
    this._solutionOverride = normalized[0] ?? null;
    if (this.isConnected) this._startGame();
  }

  get solution() {
    return this._solution;
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

  _startGame() {
    const dailyAnswer = getDailyAnswer(this._words);
    this._solution = this._solutionOverride ?? dailyAnswer;
    const solutionIndex = this._words.indexOf(this._solution);
    this._wordIndex = solutionIndex >= 0 ? solutionIndex : Math.max(0, this._words.indexOf(dailyAnswer));
    this._resetRound();
  }

  _startNextWord() {
    if (this._words.length < 2) return;

    this._wordIndex = (this._wordIndex + 1) % this._words.length;
    this._solution = this._words[this._wordIndex];
    this._solutionOverride = this._solution;
    this._resetRound();
  }

  _resetRound() {
    clearTimeout(this._revealTimer);
    this._revealTimer = null;
    this._wordLength = this._solution?.length ?? 5;
    this._guesses = [];
    this._currentGuess = "";
    this._finished = null;
    this._isRevealing = false;
    this._revealingRow = -1;
    this._pendingOutcome = null;
    this._message = this._solution ? "" : "Add a word list to start.";
    this._render();
  }

  _handleClick(event) {
    const nextButton = event.target.closest("button[data-action='next-word']");
    if (nextButton) {
      this._startNextWord();
      this.focus({ preventScroll: true });
      return;
    }

    if (!this._solution || this._finished !== null || this._isRevealing) return;
    const keyButton = event.target.closest("button[data-key]");
    if (!keyButton) return;

    const key = keyButton.dataset.key;
    if (/^[A-Z]$/.test(key) && this._keyStatuses().get(key) === "absent") return;
    if (key === "ENTER") this._submitGuess();
    else if (key === "BACKSPACE") this._currentGuess = this._currentGuess.slice(0, -1);
    else if (/^[A-Z]$/.test(key) && this._currentGuess.length < this._wordLength) {
      this._currentGuess += key;
    }

    this._render();
    this._scheduleRevealCompletion();
    this.focus({ preventScroll: true });
  }

  _handleKeydown(event) {
    if (this._isRevealing) {
      event.preventDefault();
      return;
    }
    if (event.altKey || event.ctrlKey || event.metaKey || this._finished !== null) return;

    const key = event.key.toUpperCase();
    if (key === "ENTER") {
      event.preventDefault();
      this._submitGuess();
      this._render();
      this._scheduleRevealCompletion();
      this.focus({ preventScroll: true });
    } else if (key === "BACKSPACE") {
      event.preventDefault();
      this._currentGuess = this._currentGuess.slice(0, -1);
      this._render();
      this.focus({ preventScroll: true });
    } else if (/^[A-Z]$/.test(key)) {
      event.preventDefault();
      if (this._keyStatuses().get(key) === "absent" || this._currentGuess.length >= this._wordLength) return;
      this._currentGuess += key;
      this._render();
      this.focus({ preventScroll: true });
    }
  }

  _submitGuess() {
    if (!this._solution || this._finished !== null) return;
    if (this._currentGuess.length !== this._wordLength) {
      this._message = "Not enough letters.";
      return;
    }

    const guess = this._currentGuess;
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
  }

  _scheduleRevealCompletion() {
    if (!this._isRevealing) return;

    const animationDuration = window.matchMedia("(prefers-reduced-motion: reduce)").matches
      ? 0
      : FLIP_DURATION_MS + (this._wordLength - 1) * FLIP_STAGGER_MS;

    this._revealTimer = setTimeout(() => {
      this._isRevealing = false;
      this._revealingRow = -1;
      this._finished = this._pendingOutcome;

      if (this._finished === "won") this._message = "You got it!";
      else if (this._finished === "lost") this._message = `The word was ${this._solution}.`;
      else this._message = "";

      const outcome = this._finished;
      this._pendingOutcome = null;
      this._render();
      if (outcome === "won" || outcome === "lost") this._announceCompletion(outcome === "won");
      this.focus({ preventScroll: true });
    }, animationDuration);
  }

  _announceCompletion(won) {
    this.dispatchEvent(new CustomEvent("ash-wordle-complete", {
      bubbles: true,
      composed: true,
      detail: {
        won,
        solution: this._solution,
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
      const guess = this._guesses[rowIndex] ?? (rowIndex === this._guesses.length ? this._currentGuess : "");
      const feedback = rowIndex < this._guesses.length ? scoreGuess(guess, this._solution) : [];
      const cells = Array.from({ length: this._wordLength }, (_, index) => {
        const letter = guess[index] ?? "";
        const state = feedback[index] ?? "";
        const isRevealingTile = this._isRevealing && rowIndex === this._revealingRow;
        const animationClass = isRevealingTile ? " flip-reveal" : "";
        return `<span class="tile ${state}${animationClass}" style="--tile-index: ${index}" aria-label="${letter ? `Letter ${letter}` : "Empty"}">${letter}</span>`;
      }).join("");

      rows.push(`<div class="board-row" aria-label="Guess ${rowIndex + 1}">${cells}</div>`);
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
        const disabled = state === "absent" ? " disabled" : "";
        return `<button type="button" class="key${wide} ${state}" data-key="${key}" aria-label="${label}"${disabled}>${text}</button>`;
      }).join("");

      return `<div class="keyboard-row">${buttons}</div>`;
    }).join("");
  }

  _render() {
    const isReady = Boolean(this._solution);
    const progress = `${this._guesses.length} / ${this._maxAttempts}`;

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
          grid-template-columns: repeat(${this._wordLength}, minmax(0, 1fr));
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
        .message {
          min-height: 22px;
          margin: 0 0 10px;
          color: #4b514a;
          font-size: 13px;
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
        .key:disabled { cursor: not-allowed; }
        .key:focus-visible { outline: none; }
        .next-word {
          display: block;
          margin: 8px auto 14px;
          padding: 9px 14px;
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
          .flip-reveal { animation: none; }
        }
      </style>
      <section class="game" style="--word-length: ${this._wordLength}">
        <header class="topline">
          <h2>Ash Wordle</h2>
          <span class="progress">${progress}</span>
        </header>
        <div class="board" aria-label="Wordle board">${this._renderBoard()}</div>
        <p class="message" role="status" aria-live="polite">${this._message}</p>
        ${this._finished === "won" && this._words.length > 1 ? `<button type="button" class="next-word" data-action="next-word">Next word</button>` : ""}
        ${isReady ? `<div class="keyboard" aria-label="On-screen keyboard">${this._renderKeyboard()}</div>` : ""}
      </section>
    `;
  }
}

export function defineAshWordleElement() {
  if (typeof customElements === "undefined") return;
  if (!customElements.get("ash-wordle")) {
    customElements.define("ash-wordle", AshWordleElement);
  }
}