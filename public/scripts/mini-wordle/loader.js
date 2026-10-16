import { createWordleApi, mountAshWordle } from "./index.js";
import { WORDS } from "./words.js";

const wordleApi = createWordleApi();
let activeDialog = null;

function prepareTriggers(root = document) {
  root.querySelectorAll?.(".mini-wordle-btn").forEach((trigger) => {
    if (!trigger.hasAttribute("tabindex")) trigger.tabIndex = 0;
    if (!trigger.hasAttribute("role")) trigger.setAttribute("role", "button");
    trigger.setAttribute("aria-haspopup", "dialog");
    if (!trigger.hasAttribute("aria-label")) {
      trigger.setAttribute("aria-label", "Open the mini Wordle game");
    }
  });
}

function getWordleTrigger(target) {
  const card = target?.closest?.(".surprise-card");
  if (!card) return target?.closest?.(".mini-wordle-btn") ?? null;
  if (card.classList.contains("mini-wordle-btn")) return card;
  return card.querySelector(".mini-wordle-btn");
}

function canOpenWordle(trigger) {
  const card = trigger?.closest(".surprise-card") ?? trigger;
  const isTrigger = trigger?.classList.contains("mini-wordle-btn")
    || card?.classList.contains("mini-wordle-btn");

  return isTrigger
    && card?.classList.contains("viewed")
    && !card.classList.contains("upcoming")
    && !card.classList.contains("time-to-view");
}

function openWordle(trigger) {
  if (!canOpenWordle(trigger) || activeDialog) return;

  const dialog = document.createElement("dialog");
  dialog.className = "mini-wordle-dialog";
  dialog.setAttribute("aria-label", "Ash Wordle");
  dialog.innerHTML = `
    <div class="mini-wordle-dialog__layout">
      <button type="button" class="mini-wordle-dialog__close" aria-label="Close Wordle">&times;</button>
      <div class="mini-wordle-dialog__game"></div>
    </div>
  `;

  const closeButton = dialog.querySelector(".mini-wordle-dialog__close");
  closeButton.addEventListener("click", () => dialog.close());
  dialog.addEventListener("keydown", (event) => {
    if (event.key === "Escape") {
      event.preventDefault();
      dialog.close();
    }
  });
  dialog.addEventListener("click", (event) => {
    if (event.target === dialog) dialog.close();
  });
  dialog.addEventListener("close", () => {
    dialog.querySelector("ash-wordle")?.remove();
    dialog.remove();
    activeDialog = null;
    if (trigger.isConnected) trigger.focus({ preventScroll: true });
  }, { once: true });

  document.body.append(dialog);
  activeDialog = dialog;
  dialog.showModal();

  const game = mountAshWordle(dialog.querySelector(".mini-wordle-dialog__game"), {
    words: WORDS,
    persistence: wordleApi,
  });
  game.focus({ preventScroll: true });
}

document.addEventListener("click", (event) => {
  const trigger = getWordleTrigger(event.target);
  if (!canOpenWordle(trigger)) return;
  event.preventDefault();
  openWordle(trigger);
});

document.addEventListener("keydown", (event) => {
  if (event.key !== "Enter" && event.key !== " ") return;
  const trigger = getWordleTrigger(event.target);
  if (!canOpenWordle(trigger)) return;
  event.preventDefault();
  openWordle(trigger);
});

const triggerObserver = new MutationObserver((records) => {
  for (const record of records) {
    if (record.type === "attributes") {
      prepareTriggers(record.target.parentElement ?? document);
    } else {
      record.addedNodes.forEach((node) => {
        if (node.nodeType === Node.ELEMENT_NODE) {
          prepareTriggers(node.matches?.(".mini-wordle-btn") ? node.parentElement : node);
        }
      });
    }
  }
});

prepareTriggers();
triggerObserver.observe(document.body, {
  attributes: true,
  attributeFilter: ["class"],
  childList: true,
  subtree: true,
});