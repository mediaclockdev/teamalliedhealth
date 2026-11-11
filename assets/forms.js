// Sends a validated form to /api/send. Returns true on success (caller then shows the thank-you).

// Honeypot on every form from page load, so bots that scrape the markup see (and fill) it.
// Named "_hp" so browser autofill never fills it for real people.
document.querySelectorAll("form").forEach((f) => {
  const h = document.createElement("input");
  h.name = "_hp"; h.tabIndex = -1; h.autocomplete = "off"; h.setAttribute("aria-hidden", "true");
  h.style.cssText = "position:absolute;left:-9999px";
  f.appendChild(h);
  // Firefox restores a button's disabled state on reload; this stops a stuck "disabled" button.
  const b = f.querySelector("[type=submit]");
  if (b) b.autocomplete = "off";
});

async function sendForm(f) {
  if (f.dataset.sending) return false; // already in flight: ignore repeat clicks / Enter
  f.dataset.sending = "1";
  const data = Object.fromEntries(new FormData(f));
  data._page = location.pathname.replace(/^\//, "") || "index.html";
  const btn = f.querySelector("[type=submit]");
  const thanks = f.querySelector("[role=status]");
  const label = btn && btn.innerHTML;
  if (thanks) thanks.classList.add("hidden"); // clear a previous "Thanks" before a new attempt
  if (btn) {
    btn.disabled = true;
    btn.textContent = "Sending…";
    btn.style.opacity = ".6";
    btn.style.cursor = "not-allowed";
  }
  try {
    const r = await fetch("/api/send", {
      method: "POST",
      headers: { "Content-Type": "application/json" },
      body: JSON.stringify(data),
      signal: AbortSignal.timeout(20000), // never leave the button stuck on "Sending…"
    });
    if (r.ok && (await r.json()).ok) return true;
  } catch (_) {
  } finally {
    delete f.dataset.sending;
    if (btn) {
      btn.disabled = false;
      btn.innerHTML = label;
      btn.style.opacity = btn.style.cursor = "";
    }
  }
  alert("Sorry, we couldn't send your message. Please call 0429 621 506 or try again.");
  return false;
}
