# Creative Printers — Stock & Delivery Manager (Hostinger PHP + MySQL)

This replaces the earlier Google Sheets version. It runs entirely on your existing **Single Web Hosting** plan — no upgrade needed, since PHP + MySQL are supported on every Hostinger hosting plan (Node.js/React/Next.js hosting is not, on Single plans — that needs Business or Cloud).

## What you're deploying
A folder called `app` that sits alongside your existing website files, at:
`creativeprintingsolution.in/app/`

Your existing site (`index.html`, `image/` folder, etc.) is untouched.

## Folder structure
This repo's root mirrors your hosting root:
```
public_html/
├── index.html          <- your existing marketing site (untouched)
├── image/               <- assets for index.html
└── app/                 <- this tool
    ├── includes/
    │   ├── db.php                        <- safe to commit, holds no secrets
    │   ├── db_credentials.example.php    <- template only
    │   └── auth.php
    ├── admin/
    │   ├── index.php
    │   ├── users.php
    │   ├── stock.php
    │   ├── purchase_orders.php
    │   └── deliveries.php
    ├── user/
    │   └── dues.php
    ├── login.php
    ├── logout.php
    ├── setup.php          <- run once, then DELETE
    ├── send_reminders.php  <- run automatically by a cron job
    ├── schema.sql
    └── style.css
```
(A `default.php` Hostinger onboarding placeholder used to sit at the root too — it's been removed as it wasn't part of the site or this tool.)

## Step 1 — Create the MySQL database in hPanel
1. Log in to hPanel → go to **Databases → MySQL Databases**.
2. Click **Create New Database**. Give it a name (Hostinger will prefix it, e.g. `u123456789_stockdb`).
3. Create a database **user** and password (or use the auto-created one), and make sure it's attached to that database with **All privileges**.
4. Note down: **database name**, **database username**, **database password**, and host (almost always `localhost` on shared hosting).

## Step 2 — Create the tables
1. In hPanel, open **phpMyAdmin** for that database.
2. Click **Import**, choose the `schema.sql` file from this package, and run the import.
3. You should now see 4 empty tables: `users`, `stock`, `purchase_orders`, `deliveries`.

## Step 3 — Get the code onto the server
This repo's `app/includes/db.php` holds **no real credentials** — it just loads them from a separate file, `db_credentials.php`, that you create **once, manually, one level above `public_html`** (i.e. at your Hostinger account root, the same folder `public_html` itself sits in). Keeping it there means it's completely outside anything Git/FTP deployment ever touches — a redeploy, a `git pull`, even a full clean sync can't overwrite or delete it, since it's not part of the deployed directory tree at all.

**Deploy the code** (via Git deployment, GitHub Actions FTP, or a manual upload — whichever you're using), then:

1. In hPanel **File Manager**, go to your account root (one level above `public_html` — you'll see `public_html` itself listed there).
2. Create a new file named **`db_credentials.php`** at that level (not inside `public_html`).
3. Copy the contents of `app/includes/db_credentials.example.php` into it, and fill in your real database name, username, and password from Step 1.

That's it — `db.php` (already deployed as part of the app) will pick these up automatically via `require __DIR__ . '/../../../db_credentials.php'`.

## Step 4 — Run the one-time setup
1. Visit `https://creativeprintingsolution.in/app/setup.php` in your browser.
2. It creates the first login:
   - Username: `admin`
   - Password: `ChangeMe123`
3. **Immediately log in and change this password** (there's no "edit password" screen yet — for now, delete this user via Users page after creating a new admin with your real password, or ask me to add a "change password" screen).
4. **Delete `setup.php`** from the server right after — otherwise anyone could re-run it. (Only works if the `users` table is still empty, but delete it anyway for safety.)

## Step 5 — Schedule the automatic email reminders (Cron Job)
1. In hPanel, go to **Advanced → Cron Jobs**.
2. Create a new cron job:
   - **Frequency:** once a day (e.g. every day at 9:00 AM)
   - **Command:** `php /home/YOUR_USERNAME/domains/creativeprintingsolution.in/public_html/app/send_reminders.php`
   (Hostinger shows your exact file path when you click into a folder in File Manager — copy it from there if unsure. Your hosting username is shown at the top of hPanel.)
3. Save. This runs `send_reminders.php` automatically every day — no need to visit it in a browser.

## Step 6 — Link it from your website
Add a link on your existing `index.html`, e.g.:
```html
<a href="/app/login.php">Staff Login</a>
```

## Step 7 — Start using it
1. Log in as admin at `creativeprintingsolution.in/app/login.php`.
2. Add staff logins under **Users**.
3. Add products under **Stock**.
4. Add a **Purchase Order** header (PO number, date, customer, item, total quantity).
5. Add one **Delivery Schedule** row per due date under that PO (matches your paper PO format — one PO can have several batch delivery dates, each with its own quantity).
6. Reminders go out automatically 3 days before each due date, and again on the due date itself, to all admin emails on file. Change the "3 days" window by editing this line in `send_reminders.php`:
   ```php
   $REMINDER_DAYS_BEFORE = 3;
   ```

## Notes
- **Email delivery:** this uses PHP's built-in `mail()` function, which works out of the box on Hostinger but can sometimes land in spam. If that becomes a problem, tell me and I'll switch it to send via your Hostinger email account over SMTP (more reliable) using PHPMailer.
- **Security:** passwords are hashed (not stored as plain text) — a real improvement over the Sheets version. Still, keep your hPanel and database passwords private.
- **Backups:** hPanel has automatic backups for Single Web Hosting — worth confirming they're turned on, since this is now your live business data.

## If something doesn't work
- **"Database connection failed"** → double check `db_credentials.php` at your account root (one level above `public_html`) — database name/user/password must match exactly what hPanel shows.
- **Blank page** → check hPanel's **Error Logs** (under Advanced), PHP errors get logged there.
- **Cron not sending emails** → run `send_reminders.php` manually first by visiting its URL once in the browser to confirm it works, then check the cron command path is exactly correct.
- **"Access denied"** → you're logged in as a `user` role trying to reach an admin page — that's expected, only `admin` accounts can manage data.
