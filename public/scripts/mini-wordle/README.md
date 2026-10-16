# Mini Wordle

The browser game and word-scoring logic are plain ES modules in this directory. `loader.js` watches for `.mini-wordle-btn` surprise cards and opens a full-screen game dialog only when the card also has `.viewed`.

To opt one surprise in, add `mini-wordle-btn` to that card's outer element in `buildSurpriseCard()` in `app/Views/index.php`. Because surprise cards are generated from API data, add the class only for the selected surprise. The first successful reveal marks the card viewed and flips it; later clicks open the game. The modal closes with its Close button, Escape, or a click on the backdrop.

Like Wordle, there is one five-letter word per day, and it changes at the player's local midnight. `words.js` holds the answers: `PERSONAL_WORDS` plus a list of common words. `getDailyAnswer()` in `word-logic.js` walks a seeded shuffle of that list, so no word repeats until the list runs out. Editing the list reshuffles future days, but days that were already played keep their saved word. Guesses must appear in `words.js` or `valid-guesses.js`. Otherwise the game shows "Not in word list".

Progress is saved through `wordle-api.js` to `GET`/`PUT /wordle/{YYYY-MM-DD}` (`WordleController`), which writes one `wordle_games` row per day with the guesses, the attempt count, and won/lost status. Reopening the game restores the board. A finished day shows Wordle-style statistics (played, win %, streaks, guess distribution) and a countdown to the next daily word.

After a word is finished, the "Another?" button starts a bonus round right away. Bonus words come from the same shuffle read backwards (`getBonusAnswer()`), so they stay clear of upcoming daily words. They are saved as rounds 2, 3, and so on for that day, with their own statistics, and don't affect the daily stats or streaks. Reopening the game resumes an unfinished bonus round.

Run `migrations/mysql/004_create_wordle_games.sql` and `005_add_wordle_bonus_rounds.sql` (or `php scripts/init-db.php`) to create the table.

The dialog imports the game with `mountAshWordle()` from `index.js`.