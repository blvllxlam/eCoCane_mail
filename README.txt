eCo-Cane email template

Files
- index.html — responsive, table-based HTML email markup.
- images/ — image assets used by the email.
- ref/УтверждённыйМакет.png — approved visual reference.
- style.css — browser-preview helper only; email-critical styles are in index.html.

Before sending
1. Upload every file from images/ to a public HTTPS directory on the eCo-Cane website. Suggested directory:
   https://eco-cane.group/wp-content/uploads/ecocane-mail/
2. Keep the filenames unchanged. The HTML currently points to that directory:
   https://eco-cane.group/wp-content/uploads/ecocane-mail/<filename>
3. Open each image URL in a private/incognito browser window and confirm it loads without logging in.
4. Send test messages to Gmail and Outlook (desktop and mobile). Check image loading, line wrapping, button link and mobile layout.
5. Send the final HTML through the chosen mailing platform as HTML/source code, not as a plain-text message.

Important
- The HTML uses presentation tables and inline styles for broad email-client compatibility.
- The responsive media query is an enhancement; the basic layout remains table-based if media queries are ignored.
- Images must be publicly reachable over HTTPS. Relative image paths and private GitHub repository URLs will not work for recipients.
- The call-to-action button links to https://eco-cane.group/.
- This template has not yet been verified by sending tests through Gmail/Outlook. Do not treat it as production-approved until the image URLs are live and test emails have been reviewed.
