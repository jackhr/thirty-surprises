# Mini Wordle

The browser game and word-scoring logic are plain ES modules in this directory. `loader.js` watches for `.mini-wordle-btn` surprise cards and opens a full-screen game dialog only when the card also has `.viewed`.

To opt one surprise in, add `mini-wordle-btn` to that card's outer element in `buildSurpriseCard()` in `app/Views/index.php`. Because surprise cards are generated from API data, add the class only for the selected surprise. The first successful reveal marks the card viewed and flips it; later clicks open the game. The modal closes with its Close button, Escape, or a click on the backdrop.

Edit the `WORDS` array in `loader.js` to supply the personal answer list. The dialog imports the game with `mountAshWordle()` from `index.js`.