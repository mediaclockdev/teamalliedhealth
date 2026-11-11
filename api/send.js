// Form mailer (Vercel function): emails every submitted field via SMTP. Config comes from env vars.
const nodemailer = require("nodemailer");

const LABELS = {
  for: "Enquiry is for", name: "Name", dob: "Date of birth", parent: "Parent/caregiver",
  guardian: "Parent/guardian", age: "Age", address: "Address", email: "Email", phone: "Phone",
  funding: "Funding", therapy: "Therapy", location: "Location", topic: "Topic",
  focus: "Focus", delivery: "Delivery", message: "Message", consent: "Consent",
};

module.exports = async (req, res) => {
  if (req.method !== "POST") return res.status(405).json({ ok: false });
  const b = req.body || {};
  if (b._hp) return res.json({ ok: true }); // honeypot: pretend success to bots

  const name = String(b.name || "").trim();
  const email = String(b.email || "").trim();
  if (!name || !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) return res.status(422).json({ ok: false });

  let text = `Page: ${String(b._page || "unknown").replace(/[^\w\-/. ]/g, "")}\n`;
  for (const [k, v] of Object.entries(b)) {
    if (k === "_hp" || k === "_page" || typeof v === "object") continue;
    text += `${LABELS[k] || k}: ${v === "on" ? "Yes" : String(v).trim().slice(0, 5000)}\n`;
  }

  const { SMTP_HOST, SMTP_PORT, SMTP_USER, SMTP_PASS, MAIL_TO, MAIL_FROM } = process.env;
  try {
    await nodemailer
      .createTransport({ host: SMTP_HOST, port: +SMTP_PORT, secure: +SMTP_PORT === 465, auth: { user: SMTP_USER, pass: SMTP_PASS } })
      .sendMail({
        from: MAIL_FROM || SMTP_USER,
        to: MAIL_TO || SMTP_USER,
        replyTo: email,
        subject: `New website enquiry - ${name.replace(/[\r\n]/g, "")}`,
        text,
      });
    res.json({ ok: true });
  } catch (e) {
    console.error(e);
    res.status(500).json({ ok: false });
  }
};
