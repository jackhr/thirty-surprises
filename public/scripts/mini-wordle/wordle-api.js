// Saves Ash Wordle progress through the PHP API (see WordleController).
// Each save sends the full guess list for the day, so a failed save is repaired by the next one.
export function createWordleApi(baseUrl = "/wordle") {
  async function request(url, options = {}) {
    const response = await fetch(url, {
      credentials: "same-origin",
      ...options,
      headers: {
        "X-Requested-With": "XMLHttpRequest",
        ...options.headers,
      },
    });

    let payload;
    try {
      payload = await response.json();
    } catch {
      throw new Error(`Invalid server response (${response.status})`);
    }

    // A 409 means the server already has different progress for today; hand it back to adopt.
    if (response.status === 409 && payload.game) return { ...payload, conflict: true };
    if (!response.ok || payload.error) {
      throw new Error(payload.error || `Request failed (${response.status})`);
    }

    return payload;
  }

  return {
    load(dateKey) {
      return request(`${baseUrl}/${encodeURIComponent(dateKey)}`);
    },

    // round 1 is the daily word; rounds 2+ are bonus words from the "Another?" button.
    save(dateKey, { round, solution, guesses }) {
      return request(`${baseUrl}/${encodeURIComponent(dateKey)}`, {
        method: "PUT",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({ round, solution, guesses }),
      });
    },
  };
}
