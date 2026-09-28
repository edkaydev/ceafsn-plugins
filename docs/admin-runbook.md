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
3. Either upload a CSV/ZIP via the Media Library, or enter an approved external Download URL.
4. The plugin will validate the file or URL. You cannot publish if validation fails.
5. Set Status to **Published** and click **Save**.

> **Important:** Never enter a download URL that returns a 404 or redirects to a placeholder.

---

## 4. Projects and Publications (`ceafsn-projects-publications`)

### Adding a Publication
1. Go to **CE-AFSN → Publications → Add New**.
2. Fill in all required fields.
3. Upload the PDF from the Media Library.
4. The plugin validates the PDF. You cannot publish if validation fails.
5. If the same PDF is intentionally shared with another record, check **Duplicate Document** and enter a note explaining why.
6. Set Status and Access Level, then click **Publish**.

### Updating the `/appy` Destination
1. Go to **CE-AFSN → Publications → Settings → Apply Link**.
2. Enter the correct application destination URL.
3. Save. The Apply Now buttons across the site will now point to this URL.

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
