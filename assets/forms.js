// Sends a validated form to /api/send. Returns true on success (caller then shows the thank-you).

// Honeypot on every form from page load, so bots that scrape the markup see (and fill) it.
// Named "_hp" so browser autofill never fills it for real people.
document.querySelectorAll("form").forEach((f) => {
  const h = document.createElement("input");
  h.name = "_hp";
  h.tabIndex = -1;
  h.autocomplete = "off";
  h.setAttribute("aria-hidden", "true");
  h.style.cssText = "position:absolute;left:-9999px";
  f.appendChild(h);
  // Firefox restores a button's disabled state on reload; this stops a stuck "disabled" button.
  const b = f.querySelector("[type=submit]");
  if (b) b.autocomplete = "off";
});

// Phone fields: numbers only, max 10 digits, auto-formatted as 0000 000 000.
document.querySelectorAll('input[name="phone"]').forEach((i) => {
  i.addEventListener("input", () => {
    const d = i.value.replace(/\D/g, "").slice(0, 10);
    i.value = [d.slice(0, 4), d.slice(4, 7), d.slice(7, 10)]
      .filter(Boolean)
      .join(" ");
  });
});

// Date fields: open the calendar picker when the field is clicked or focused by keyboard.
document.querySelectorAll('input[type="date"]').forEach((i) => {
  const open = () => {
    try {
      i.showPicker();
    } catch (_) {}
  };
  i.addEventListener("click", open);
  i.addEventListener("focus", open);
});

// Name fields (full name, parent/guardian/caregiver): letters, spaces, ' - . only; at least 2 letters.
document
  .querySelectorAll(
    'input[name="name"],input[name="parent"],input[name="guardian"]',
  )
  .forEach((i) => {
    const check = () => {
      i.value = i.value
        .replace(/[^\p{L}\s'’.\-]/gu, "")
        .replace(/\s{2,}/g, " ");
      i.setCustomValidity(
        i.value.trim() && (i.value.match(/\p{L}/gu) || []).length < 2
          ? "Please enter a valid name (letters only)."
          : "",
      );
    };
    i.addEventListener("input", check);
    i.addEventListener("blur", check);
  });

// Age / DOB free-text field: an age (e.g. "3", "6 years old") or a real past date (e.g. 14/05/2018, 12 Jan 2022).
document.querySelectorAll('input[name="age"]').forEach((i) => {
  const check = () => {
    const v = i.value.trim();
    let ok = !v;
    if (v) {
      const age = v.match(/^(\d{1,3})(\s*(years?|yrs?|y\/?o|years?\s*old))?$/i);
      const dmy = v.match(/^(\d{1,2})[\/\-.](\d{1,2})[\/\-.](\d{4})$/);
      const d = dmy
        ? new Date(+dmy[3], dmy[2] - 1, +dmy[1])
        : /\d{4}/.test(v) && /[a-z]/i.test(v)
          ? new Date(v)
          : null;
      if (age) ok = +age[1] >= 0 && +age[1] <= 120;
      else if (d && !isNaN(d))
        ok =
          d <= new Date() &&
          d.getFullYear() >= 1900 &&
          (!dmy || (d.getDate() === +dmy[1] && d.getMonth() === dmy[2] - 1));
    }
    i.setCustomValidity(
      ok ? "" : "Enter an age (e.g. 6 years) or a valid past date of birth.",
    );
  };
  i.addEventListener("input", check);
  i.addEventListener("blur", check);
});

// Date of birth pickers: no future dates, nothing before 1900.
document.querySelectorAll('input[type="date"]').forEach((i) => {
  i.max = new Date().toISOString().slice(0, 10);
  i.min = i.min || "1900-01-01";
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
    const r = await fetch("send.php", {
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
  alert(
    "Sorry, we couldn't send your message. Please call 0429 621 506 or try again.",
  );
  return false;
}
