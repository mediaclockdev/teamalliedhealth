// Form mailer (Vercel function): emails every submitted field via SMTP. Config comes from env vars.
const nodemailer = require("nodemailer");

const LABELS = {
  for: "Enquiry is for", name: "Name", dob: "Date of birth", parent: "Parent/caregiver",
  guardian: "Parent/guardian", age: "Age", address: "Address", email: "Email", phone: "Phone",
  funding: "Funding", therapy: "Therapy", location: "Location", topic: "Topic",
  focus: "Focus", delivery: "Delivery", message: "Message", consent: "Consent",
};

const SERVICES = {
  index: "Referral", contact: "Contact", "early-intervention": "Early Intervention",
  "occupational-therapy": "Occupational Therapy", physiotherapy: "Physiotherapy", "speech-therapy": "Speech Therapy",
};

module.exports = async (req, res) => {
  if (req.method !== "POST") return res.status(405).json({ ok: false });
  const b = req.body || {};
  if (b._hp) return res.json({ ok: true }); // honeypot: pretend success to bots

  const name = String(b.name || "").trim();
  const email = String(b.email || "").trim();
  if (!name || !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) return res.status(422).json({ ok: false });

  const page = String(b._page || "").replace(/\.html$/, "");
  const service = SERVICES[page] || "Website";
  const esc = (v) => String(v).replace(/[&<>"']/g, (c) => ({ "&": "&amp;", "<": "&lt;", ">": "&gt;", '"': "&quot;", "'": "&#39;" })[c]);
  const val = (v) => (v === "on" ? "Yes" : String(v).trim().slice(0, 5000));
  const skip = (k, v) => k === "_hp" || k === "_page" || typeof v === "object" || !String(v).trim();

  // Name, email, phone first; message last in its own block; everything else in form order.
  const TOP = ["name", "email", "phone"];
  const rest = Object.keys(b).filter((k) => !TOP.includes(k) && k !== "message" && !skip(k, b[k]));
  const rows = [...TOP, ...rest].filter((k) => b[k] !== undefined && !skip(k, b[k])).map((k) => [LABELS[k] || k, val(b[k])]);
  const message = b.message && !skip("message", b.message) ? val(b.message) : "";

  const text =
    `${service} enquiry\n\n` + rows.map(([l, v]) => `${l}: ${v}`).join("\n") + (message ? `\n\nMessage:\n${message}` : "") + "\n";

  const name_ = `<span style="font-size:24px;font-weight:700;color:#fff;letter-spacing:.2px">Team Allied Health</span>`;
  const logo = process.env.SITE_URL
    ? `<table role="presentation" cellpadding="0" cellspacing="0"><tr><td style="padding-right:14px;vertical-align:middle"><img src="${esc(process.env.SITE_URL.replace(/\/$/, ""))}/assets/logo-email.png" alt="" width="88" height="88" style="display:block;border:3px solid #fff;width:82px;height:82px;border-radius:50%"></td><td style="vertical-align:middle">${name_}</td></tr></table>`
    : name_;
  const html = `<!doctype html><html><body style="margin:0;padding:24px 12px;background:#f1f5f9;font-family:Arial,Helvetica,sans-serif;color:#0f172a">
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:600px;margin:0 auto;background:#fff;border-radius:12px;overflow:hidden">
<tr><td style="background:#004719;padding:20px 24px">${logo}</td></tr>
<tr><td style="padding:24px 24px 8px"><h2 style="margin:0;font-size:20px;color:#004719">New ${esc(service)} enquiry</h2></td></tr>
<tr><td style="padding:8px 24px 24px"><table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="border-collapse:collapse">
${rows.map(([l, v]) => `<tr><td style="padding:10px 12px;border-bottom:1px solid #e2e8f0;width:38%;font-weight:700;color:#475569;vertical-align:top">${esc(l)}</td><td style="padding:10px 12px;border-bottom:1px solid #e2e8f0;vertical-align:top">${esc(v)}</td></tr>`).join("\n")}
</table>
${message ? `<div style="margin-top:16px;padding:14px 16px;background:#f0fdf4;border-left:4px solid #047857;border-radius:6px"><div style="font-weight:700;color:#475569;margin-bottom:6px">Message</div><div style="white-space:pre-wrap">${esc(message)}</div></div>` : ""}
</td></tr>
<tr><td style="padding:14px 24px;background:#f8fafc;font-size:12px;color:#64748b">Sent from the website form. Reply to this email to respond to the enquirer.</td></tr>
</table></body></html>`;

  const { SMTP_HOST, SMTP_PORT, SMTP_USER, SMTP_PASS, MAIL_TO, MAIL_FROM } = process.env;
  try {
    await nodemailer
      .createTransport({ host: SMTP_HOST, port: +SMTP_PORT, secure: +SMTP_PORT === 465, auth: { user: SMTP_USER, pass: SMTP_PASS } })
      .sendMail({
        from: MAIL_FROM || SMTP_USER,
        to: MAIL_TO || SMTP_USER,
        replyTo: email,
        subject: `New ${service} enquiry – ${name.replace(/[\r\n]/g, "")}`,
        text,
        html,
      });
    res.json({ ok: true });
  } catch (e) {
    console.error(e);
    res.status(500).json({ ok: false });
  }
};
