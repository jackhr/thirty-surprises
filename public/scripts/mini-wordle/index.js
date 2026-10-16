import { AshWordleElement, defineAshWordleElement } from "./ash-wordle.js";
import { createWordleApi } from "./wordle-api.js";
import { getBonusAnswer, getDailyAnswer, normalizeWordList, scoreGuess } from "./word-logic.js";

defineAshWordleElement();

export { AshWordleElement, createWordleApi, getBonusAnswer, getDailyAnswer, normalizeWordList, scoreGuess };

export function createAshWordle(options = {}) {
  defineAshWordleElement();
  const game = document.createElement("ash-wordle");
  if (options.persistence) game.persistence = options.persistence;
  game.words = options.words ?? [];
  if (options.solution) game.solution = options.solution;
  if (options.maxAttempts) game.maxAttempts = options.maxAttempts;
  return game;
}

export function mountAshWordle(container, options = {}) {
  const target = typeof container === "string"
    ? document.querySelector(container)
    : container;

  if (!target || typeof target.appendChild !== "function") {
    throw new TypeError("Pass a container element or a selector for one.");
  }

  const game = createAshWordle(options);
  target.appendChild(game);
  return game;
}