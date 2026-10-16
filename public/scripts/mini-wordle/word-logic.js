export const WORD_LENGTH = 5;

const WORD_PATTERN = new RegExp(`^[A-Z]{${WORD_LENGTH}}$`);
const DAY_MS = 86_400_000;
// Ash Wordle #1. Changing either constant reshuffles every day that has not been played yet.
const PUZZLE_EPOCH = Date.UTC(2026, 9, 15);
const SHUFFLE_SEED = 30;
const shuffledLists = new WeakMap();

export function normalizeWordList(words) {
  if (!Array.isArray(words)) return [];

  const normalized = words
    .filter((word) => typeof word === "string")
    .map((word) => word.trim().toUpperCase())
    .filter((word) => WORD_PATTERN.test(word));

  return [...new Set(normalized)];
}

export function isWellFormedGuess(word) {
  return WORD_PATTERN.test(word);
}

// The player's local calendar day, so the word changes at their midnight like Wordle.
export function toDateKey(date = new Date()) {
  const year = date.getFullYear();
  const month = String(date.getMonth() + 1).padStart(2, "0");
  const day = String(date.getDate()).padStart(2, "0");
  return `${year}-${month}-${day}`;
}

export function getPuzzleNumber(date = new Date()) {
  const localDay = Date.UTC(date.getFullYear(), date.getMonth(), date.getDate());
  return Math.floor((localDay - PUZZLE_EPOCH) / DAY_MS) + 1;
}

export function msUntilNextPuzzle(date = new Date()) {
  const nextMidnight = new Date(date.getFullYear(), date.getMonth(), date.getDate() + 1);
  return nextMidnight.getTime() - date.getTime();
}

// Walks a fixed shuffle of the list one word per day, so no word repeats until the list runs out.
export function getDailyAnswer(words, date = new Date()) {
  if (words.length === 0) return null;

  const order = shuffled(words);
  const dayIndex = getPuzzleNumber(date) - 1;
  const index = ((dayIndex % order.length) + order.length) % order.length;
  return order[index];
}

// Bonus words walk the same shuffle backwards from the end, so they stay clear of the daily
// schedule for years and do not repeat each other until the list runs out.
export function getBonusAnswer(words, bonusIndex, excluded = []) {
  if (words.length === 0) return null;

  const order = shuffled(words);
  for (let step = 0; step < order.length; step += 1) {
    const offset = (((bonusIndex + step) % order.length) + order.length) % order.length;
    const word = order[order.length - 1 - offset];
    if (!excluded.includes(word)) return word;
  }

  return order[0];
}

export function scoreGuess(guess, solution) {
  const result = Array(guess.length).fill("absent");
  const remainingLetters = new Map();

  for (let index = 0; index < solution.length; index += 1) {
    if (guess[index] === solution[index]) {
      result[index] = "correct";
    } else {
      const letter = solution[index];
      remainingLetters.set(letter, (remainingLetters.get(letter) ?? 0) + 1);
    }
  }

  for (let index = 0; index < guess.length; index += 1) {
    if (result[index] === "correct") continue;

    const letterCount = remainingLetters.get(guess[index]) ?? 0;
    if (letterCount > 0) {
      result[index] = "present";
      remainingLetters.set(guess[index], letterCount - 1);
    }
  }

  return result;
}

function shuffled(words) {
  if (shuffledLists.has(words)) return shuffledLists.get(words);

  const order = [...words];
  const random = mulberry32(SHUFFLE_SEED);
  for (let index = order.length - 1; index > 0; index -= 1) {
    const swapIndex = Math.floor(random() * (index + 1));
    [order[index], order[swapIndex]] = [order[swapIndex], order[index]];
  }

  shuffledLists.set(words, order);
  return order;
}

function mulberry32(seed) {
  let state = seed >>> 0;
  return () => {
    state = (state + 0x6d2b79f5) >>> 0;
    let value = state;
    value = Math.imul(value ^ (value >>> 15), value | 1);
    value ^= value + Math.imul(value ^ (value >>> 7), value | 61);
    return ((value ^ (value >>> 14)) >>> 0) / 4_294_967_296;
  };
}
