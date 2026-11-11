eCoCane email template — GitHub Pages preview

Files
- index.html — browser preview of the approved email layout.
- images/ — image assets used by the preview.
- style.css — browser-preview helper only.

GitHub Pages preview
1. In repository Settings → Pages, select Deploy from a branch.
2. Select branch main and folder /(root), then Save.
3. After GitHub finishes publishing, open the Pages URL shown in Settings → Pages.

Important
- This repository is a preview copy. Image paths in index.html are relative (./images/...) so GitHub Pages can display the assets.
- Do not use this preview copy as the final HTML for sending email. The production email version in the main eCoCane repository uses absolute HTTPS image URLs.
- The original email markup uses presentation tables and inline styles for email-client compatibility.
- The template has not yet been verified by sending tests through Gmail/Outlook.
- The call-to-action button links to https://eco-cane.group/.
