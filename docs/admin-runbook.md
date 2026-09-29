# Admin Runbook

Step-by-step guide for CE-AFSN staff to publish and manage content across all six plugins.

---

## General Principles

- Never publish a record with placeholder, invented, or unverified data.
- Always upload real, validated files before publishing.
- If real data is not yet available, save the record as **Draft** and leave it unpublished.
- Use the **Export** function before making bulk changes or before uninstalling any plugin.

---

## 1. M&E Dashboard (`ceafsn-me-dashboard`)

### Publishing a Metric
1. Go to **CE-AFSN → M&E Dashboard → Metrics → Add New**.
2. Fill in: Label, Value, Unit (optional), Definition (optional), Source, Reporting Period.
3. Set Visibility to **Public** only when the value is verified and approved.
4. Click **Publish**.
5. The metric appears on `/me-dashboard/` under the Overview tab.

### Publishing a Demographic Group
1. Go to **CE-AFSN → M&E Dashboard → Demographics → Add New**.
2. Fill in all required fields.
3. Publish only when values are verified.

### Publishing a Project
1. Go to **CE-AFSN → M&E Dashboard → Projects → Add New**.
2. Fill in all required fields. Set Verification Status appropriately.
3. Publish only when the project record is confirmed.

---

## 2. Nutrition Policy (`ceafsn-nutrition-policy`)

### Adding a Policy Record
1. Go to **CE-AFSN → Nutrition Policy → Add New**.
2. Fill in: Title, Description, Publication Date, Topic, Authoring Institution.
3. Upload the PDF from the **Media Library**.
4. The plugin will validate the PDF. You cannot publish if validation fails.
5. Set Status to **Published** and click **Save**.

---

## 3. Open Datasets (`ceafsn-open-datasets`)

### Adding a Dataset
1. Go to **CE-AFSN → Open Datasets → Add New**.
2. Fill in all required fields.
3. Either upload a CSV, ZIP, or XLSX via the Media Library, or enter an external Download URL.
4. Click **Validate & Save**. The plugin checks the file's structure, or for a URL that it
   returns HTTP 200, reports a size, and serves a content type matching the file type you
   selected. The file size is measured or reported, never typed in.
5. Set Status to **Published** and click **Save**.

If validation fails, the record is saved as **Draft** with the reason shown above the form.
Nothing is published with a broken download.

> **Important:** Never enter a download URL that returns a 404 or redirects to a placeholder.
> The URL is fetched by the server during the save only, so it must be reachable from the web
> server, not just from your own machine.

**"Other" file type:** hidden and rejected unless you enable it under
**CE-AFSN → Open Datasets → Settings → Enable "other" file type**. It bypasses every structural
check, so leave it off unless you have a reason and will review those records yourself.

**File deleted from the Media Library:** the row renders as "Download currently unavailable"
rather than a broken link. Restore the file or switch the record to a URL.

---

## 4. Projects and Publications (`ceafsn-projects-publications`)

Menu: **Projects & Publications** → **All Records** and **Settings**.

### Adding a Publication
1. Go to **Projects & Publications → All Records → Add New**.
2. Fill in the required fields: title, content type, executive summary, author or
   institution, publication date, and project status.
3. Attach the PDF from the Media Library with **Select PDF**.
4. Optionally attach a cover image. When you do, **Cover image alt text** becomes
   required and describes what the image shows.
5. Leave **Page count** at 0 if you do not know it: the plugin measures the
   document and stores the real number. If you do enter a number, it must match
   the document or publishing is refused.
6. For a scanned, image-only PDF, tick **Scanned document**. The plugin otherwise
   refuses to publish a PDF with no extractable text, because such a file is
   usually a placeholder or a broken export.
7. If the same PDF is intentionally shared with another record, tick **This
   document is intentionally shared** and write a note explaining why. Visitors
   then see a shared-document badge on the card.
8. Set the publication state to **Published**, choose the access level, and save.

If validation fails, the record is saved as a **draft** and the reason is shown
in the notice above the form. Nothing is silently dropped.

### Placeholder Files
The old site pointed many records at the same placeholder file. **Settings →
General** lists the file names treated as placeholders. A record using one of
them cannot be published unless **Confirm this document is intentional** is
ticked and a note explains why. Add or remove names on this screen as the
library is cleaned up.

### Known Placeholder Confirmation
A file on the placeholder list is only ever a placeholder by name. If a genuine
document happens to share that name, tick the confirmation and note the reason;
it is then published like any other document.

### The Legacy Redirect
**Settings → Routes** controls the 301 from `/privacy-policy-2/` to
`/publications/`. It is on by default. Untick it to stop redirecting.

- A real page created at `/privacy-policy-2/` always wins over the redirect.
- Deactivating the plugin deletes no data, including this preference, but a
  deactivated plugin does not run, so the redirect stops until it is reactivated.

### Cover Images
`/publications/` shows a card image only when a cover is attached. Without one,
the card is text-only rather than showing a broken or generic image.

### Exporting Records
**Settings → Export** downloads every record as JSON, including drafts and
members-only records. Use it before deactivating or uninstalling.

### Uninstalling
**Settings → Uninstall** removes the plugin's table only when the opt-in box is
ticked. Media Library files are never deleted. See "Uninstalling a Plugin" below.

---

## 5. Research Fellowships (`ceafsn-research-fellowships`)

### Adding a Fellowship
1. Go to **CE-AFSN → Fellowships → Add New**.
2. Fill in required fields.
3. Set Opening Date and Closing Date. Status will be computed automatically.
4. If you need to manually set a status without dates, check **Status Override** and enter an audit note explaining why.
5. Only set the Apply button URL if you have a verified, working application link.
6. Publish when ready.

---

## 6. Grants and Funding (`ceafsn-grants-funding`)

### Adding a Grant
1. Go to **CE-AFSN → Grants → Add New**.
2. Fill in all required fields. Use **"Not disclosed"** for award range if the amount is unknown.
3. Upload the Official Call PDF. Validation runs on save.
4. Set Deadline with the correct timezone (defaults to site timezone from WP Settings → General).
5. Publish when ready.

---

## Exporting Records

Each plugin provides an Export function:
1. Go to the plugin's admin screen → **Settings → Export**.
2. Choose format: JSON or CSV.
3. Click **Export**. Save the file before any bulk changes or uninstalls.

---

## Uninstalling a Plugin

> **Warning:** Uninstalling permanently deletes all plugin data. This cannot be undone.

1. Export all records first (see above).
2. Go to the plugin's admin screen → **Settings → Uninstall**.
3. Read the warning, check the confirmation box, and click **Delete All Data**.
4. Then deactivate and delete the plugin from **Plugins → Installed Plugins**.

---

## Flushing Permalinks

After activating a plugin or creating a new page:
1. Go to **Settings → Permalinks**.
2. Click **Save Changes** (no changes needed — just saving flushes the rewrite rules).
