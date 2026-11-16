<?php
// Copy to mail-config.php and fill in. On SiteGround, upload mail-config.php ONE LEVEL ABOVE public_html.
return [
  'host'     => 'mail.teamalliedhealth.com.au',   // SiteGround: Site Tools > Email > Accounts > Mail Configuration
  'port'     => 465,                              // 465 = SSL, 587 = STARTTLS
  'user'     => 'info@teamalliedhealth.com.au',
  'pass'     => 'mailbox-password',
  'from'     => 'info@teamalliedhealth.com.au',   // must be that mailbox / your domain
  'to'       => 'info@teamalliedhealth.com.au',   // where enquiries are delivered
  'site_url' => 'https://teamalliedhealth.com.au', // used for the logo in the email
];
