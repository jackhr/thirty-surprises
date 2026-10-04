const WORD_PATTERN = /^[A-Z]+$/;

export function normalizeWordList(words) {
  if (!Array.isArray(words)) return [];

  return words
    .filter((word) => typeof word === "string")
    .map((word) => word.trim().toUpperCase())
    .filter((word) => WORD_PATTERN.test(word) && word.length <= 6);
}

export function getDailyAnswer(words, date = new Date()) {
  if (words.length === 0) return null;

  const dayNumber = Math.floor(date.getTime() / 86_400_000);
  const index = ((dayNumber % words.length) + words.length) % words.length;
  return words[index];
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