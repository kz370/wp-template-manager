=== AnyPage Header Footer for Elementor ===
Contributors: kzdev
Tags: elementor, header, footer, templates
Requires at least: 5.0
Tested up to: 6.9
Stable tag: 1.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

# AnyPage Header Footer for Elementor

Create and manage custom page templates with Elementor header and footer support from a single admin page.

---

## 🚀 Overview

**AnyPage Header Footer for Elementor** lets you:

* Create reusable custom page templates
* Assign specific pages or posts as headers and footers
* Manage templates from one simple admin interface
* Store templates in the WordPress database (no template file generation)

---

## ✨ Features

* Centralized template management UI
* Use Elementor or WordPress content as header/footer
* Header/Footer selection automatically filters content containing:

  * “header” for headers
  * “footer” for footers
* Real-time duplicate name validation
* Searchable header and footer selection
* Database-only storage
* View where templates are used across your site
* Works with all public post types
* Safe fallback to theme header/footer

---

## 🧠 Storage

Templates are stored in the database only.

* No PHP template files are generated in theme or plugin directories
* No direct file editing required
* Faster and safer updates from the admin UI

---

## 🖥️ Usage

### Create a Template

1. Go to **WP Admin → AnyPage Header & Footer**
2. Click **Add Template**
3. Enter a name and select header/footer
4. Save

---

### Apply a Template to a Page

After creating a template, assign it to any page:

1. Go to **Pages → Edit Page**
2. In the right sidebar, find **Template** (Page Attributes)
3. Select your custom template (e.g. your custom template name)
4. Click **Update**

The selected header and footer will now be applied to that page.

---

### Using with Elementor

If you're editing with Elementor:

1. Click **Edit with Elementor**
2. Open **Page Settings** (⚙️ bottom-left)
3. Set **Page Layout → Default**
4. Update the page
5. Then assign the template from the WordPress editor

---

### Manage Templates

* Edit or delete templates from the list
* Click **Used By** to see where a template is applied

---

## 🧩 Header & Footer Sources

You can use:

* Elementor templates (header/footer type)
* WordPress pages or posts that include “header” or “footer” in the title

---

## 🛡️ Notes

* Templates work across all public post types
* File-based templates take priority over database ones
* If a header or footer is missing, the theme default is used

---

## 🔧 Requirements

* WordPress 5.0 or higher
* PHP 8.0 or higher
* Elementor (optional)

---

## 📌 Key Advantage

You can create multiple templates with different headers and footers and assign them per page.

* Each page can have a different header and footer
* No need for complex theme builders

Simply change the page template to switch layouts.

---

## 📄 License

GPLv2 or later
https://www.gnu.org/licenses/gpl-2.0.html
