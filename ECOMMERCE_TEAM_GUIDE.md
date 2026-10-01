# Ecommerce Module — Team Guide (SOP)

This is the official step-by-step guide for using the Ecommerce section of Laleen Ops. It covers everything the team needs for day-to-day use: tracking partner investments, logging online expenses, and managing raw materials and finished products.

No technical knowledge is needed — just follow the steps below in order.

---

## 1. Getting Access

The Ecommerce section only appears in the left-hand sidebar if your account has been given access to it.

- **No Access** — you won't see the "Ecommerce" link at all.
- **View Only** — you can open every screen and see all the numbers, but you won't see any "Add," "Log," or trash-can (delete) buttons.
- **Full Edit** — you can see everything *and* add, log, and delete entries.

If you don't see "Ecommerce" in the sidebar, or you can see it but can't add anything, ask an admin to update your permissions under **Staff Management → Staff Access**.

Once you have access, click **Ecommerce** in the sidebar. You'll land on the **Partner Ledger** — this is the home screen for the whole module.

At the top of every Ecommerce screen you'll see three tabs:

| Tab | What it's for |
|---|---|
| **Partner Ledger** | Who has put money in, how much, and whether everyone's contribution is fair |
| **Expenses** | Every online-only cost the business has paid for |
| **Inventory & Production** | Raw materials, finished products, and turning one into the other |

You can switch between them at any time — nothing you're doing gets lost when you switch tabs unless you close a form without saving.

---

## 2. Partner Ledger

This screen answers: *how much has each partner put in, and is it fair?*

### 2.1 What the cards mean

- **Total Capital Pool** — the total cash every partner has ever put into the business.
- **Available Balance (Runway)** — how much of that cash is still unspent (Total Capital Pool minus everything paid for using the Partner Ledger — see the Expenses section below for why some expenses don't count here).
- **One card per partner** — shows how much that partner has personally contributed, what percent of the total pool that is, and whether they're "Fully Matched" with the partner who has put in the most, or whether they still owe money to catch up.

### 2.2 Adding a new partner

1. Click **+ Add Partner** (top right).
2. Fill in their **Name** and, optionally, their **Email**.
3. Leave **Equity %** as the default unless you've been told a different split — this only affects reporting, not the matching logic above.
4. Click **Save**.

### 2.3 Logging money a partner has put in

Every time a partner transfers money into the business, log it here so the ledger stays accurate.

1. Click **+ Add Transaction**.
2. Choose the **Partner** who put the money in.
3. Enter the **Amount** in PKR.
4. Choose a **Category** that best describes what the money was for (e.g. Initial Inventory, Ad Budget, Packaging Run).
5. Optionally add a **Reference Note** (e.g. "Bank transfer ref #1234").
6. Confirm or change the **Date**.
7. Click **Save**.

The partner's card and the Total Capital Pool update immediately.

> **Note:** This screen is only for money coming *in* from partners. There is no "money going out to a partner" option here — only capital injections are tracked.

### 2.4 Deleting a transaction

If a transaction was logged by mistake, find it in the **Transaction History** table and click the trash can icon on its row. You'll be asked to confirm — this cannot be undone, so double-check before confirming.

---

## 3. Expenses

This screen tracks every cost related to running the ecommerce side of the business, kept separate from normal salon expenses.

### 3.1 What the cards mean

- **Total Capital Pool** — same number as on the Partner Ledger.
- **Total Ecommerce Expenses** — everything ever logged on this page, regardless of how it was paid for.
- **Available Balance (Runway)** — same number as on the Partner Ledger. This only goes down when an expense is marked as paid from the **Partner Ledger** (see step 3.2 below) — expenses paid from **Sales Revenue** don't reduce it, because that money never came from the partners' pool in the first place.

### 3.2 Logging an expense

1. Click **+ Log Expense**.
2. Enter a short **Title** (e.g. "Facebook Ads — October").
3. Optionally add a **Description / Note** with more detail on what it was for.
4. Choose a **Category**:
   - Pick one from the dropdown if it already exists, **or**
   - Select **+ Add New Category** at the bottom of the list, and a text box will appear — type your new category name there. It will be remembered and show up in the dropdown for next time.
5. Choose **Paid Using**:
   - **Partner Ledger (Total Pool)** — if the money came out of the partners' shared cash. This reduces the Available Balance.
   - **Sales Revenue** — if it was paid for using money the business has already earned from sales. This does *not* reduce the Available Balance.
6. Enter the **Amount** in PKR.
7. Optionally enter the **Vendor** name.
8. Confirm or change the **Date**.
9. Optionally attach a **Receipt** (PDF, JPG, or PNG, up to 5MB).
10. Click **Save**.

### 3.3 Deleting an expense

Find the row in the table and click the trash can icon, then confirm. This cannot be undone.

---

## 4. Inventory & Production

This screen has two parts: **Tier 1 — Raw Materials** (the ingredients and supplies you buy) and **Tier 2 — Finished Goods** (the products you make and sell), plus a way to log each time you turn raw materials into finished stock.

### 4.1 Tier 1 — Raw Materials

Each row shows a material's **Category** and its **Remaining / Original** stock — for example, "1,800.00 / 2,000.00 ml" means you started with 2,000 ml and have 1,800 ml left. The "Original" number never changes once set, so you can always see how much you started with.

**Adding a raw material:**

1. Click **+ Add Raw Material**.
2. Choose a **Category**:
   - Pick an existing one from the dropdown, **or**
   - Select **+ Add New Category** and type a new category name in the box that appears.
3. Enter the **Current Stock** you're starting with (this also becomes the "Original" amount).
4. Enter the **Unit of Measure** (e.g. ml, units, boxes).
5. Click **Save**.

**Deleting a raw material:** click the trash can icon on its row and confirm. This permanently removes it, so only do this for materials you've genuinely stopped using.

### 4.2 Tier 2 — Finished Goods

Each row shows a finished product's **Name**, **SKU** (its product code), and **Current Stock**.

**Adding a finished product:**

1. Click **+ Add Finished Product**.
2. Enter the **Product Name**.
3. Enter a unique **SKU** (e.g. "RS-100"). Every product needs its own SKU — the system won't let two products share one.
4. Click **Save**.

New products start with zero stock. Stock only increases when you log a production run (see 4.3 below).

**Deleting a finished product:** click the trash can icon on its row and confirm.

> ⚠️ **Important:** Deleting a finished product automatically refunds every raw material that was ever used to make it, back onto the Raw Materials stock. For example, if that product's production history used 1,000 ml of Oil in total, deleting it adds 1,000 ml back onto the Oil row. Only delete a product if you genuinely want that history and its stock reversed — this cannot be undone.

### 4.3 Logging a Production Run

Use this every time you actually make a batch of a finished product, to keep both stock counts accurate.

1. Click **+ Log Production Run** (top right of the Inventory & Production screen).
2. **Section A — What was produced?**
   - Choose the **Finished Product** you made.
   - Enter the **Quantity Produced** (how many units came out of this batch).
3. **Section B — Raw materials used for this entire batch**
   - Click **+ Add Raw Material** for each ingredient or supply you used.
   - For each one, select the **Raw Material** and enter the **Total used** — this is the *total amount used for the whole batch*, not the amount per single unit. For example, if you used 1,000 ml of Oil to make the whole batch of 20 bottles, enter 1,000 — not 50.
   - Add as many materials as you need, or click the **×** button to remove a row you added by mistake.
4. Click **Log Run**.

Behind the scenes, this does two things at once:
- Adds the quantity produced onto the finished product's Current Stock.
- Subtracts the exact amount you entered for each raw material from its Current Stock.

---

## 5. Frequently Asked Questions

**Why doesn't my Available Balance change when I log an expense?**
Check how the expense was marked under "Paid Using." Only expenses paid from the **Partner Ledger** reduce the Available Balance. Expenses paid from **Sales Revenue** are tracked but don't touch the partner pool.

**I can't see "+ Add" or trash can buttons — why?**
Your account likely has **View Only** access to Ecommerce. Ask an admin to upgrade you to **Full Edit** under Staff Management → Staff Access if you need to make changes.

**What happens if I enter the wrong amount in a Production Run?**
There's currently no "edit" option for a production run. Log the correct run, and if the stock numbers are now off, use the trash can on the affected raw material or finished product rows to delete and re-add them with the right numbers, or ask an admin for help correcting the stock directly.

**Does deleting a raw material affect anything else?**
No — deleting a raw material just removes that row. It does not change any finished product's stock.

**What does "Fully Matched" mean on a partner's card?**
It means that partner has put in at least as much as the partner who has contributed the most. If a partner hasn't caught up yet, their card shows how much more they owe to match the top investor.

---

## 6. Need Help?

If something looks wrong, a number doesn't add up, or a button isn't working the way this guide describes, contact an admin rather than trying to fix it by deleting and re-adding records — several actions on this page (like deleting a finished product) cannot be undone.
