# API Changes: Competency Admin Role (Frontend)

This document describes API changes for the new **Competency Admin** role. Competency admins use the same registration flow, the same backend entity (ACC), and the same API routes as **ACC Admin** — only the `role` value differs.

---

## 1. Overview

| Item | Description |
|------|-------------|
| **New role value** | `competency_admin` |
| **Behavior** | Same as `acc_admin`: creates an ACC record, uses `/api/acc/*` and shared admin routes, requires group admin approval. |
| **User object** | `user.role` may now be `"competency_admin"` in addition to `training_center_admin`, `acc_admin`, `group_admin`, `instructor`. |

---

## 2. Registration

### `POST /api/auth/register`

**Request (multipart/form-data):**

- **`role`** — allowed values are now:
  - `training_center_admin`
  - `acc_admin`
  - **`competency_admin`** *(new)*

When `role` is **`competency_admin`**:

- Use the **same payload as `acc_admin`** (same required/optional fields).
- Required: `legal_name`, `acc_email`, physical & mailing address, primary & secondary contact, both passports, company registration certificate, agreements, etc.
- Backend creates a **User** with `role: "competency_admin"` and an **ACC** record (same table as for ACC admins). The user is linked to the ACC by email (same as ACC).

**Example (minimal):**

```json
{
  "name": "Competency Body Name",
  "email": "admin@competencybody.com",
  "password": "********",
  "password_confirmation": "********",
  "role": "competency_admin",
  "legal_name": "Competency Body Legal Name",
  "acc_email": "admin@competencybody.com",
  "telephone_number": "+1234567890",
  "address": "...",
  "city": "...",
  "country": "...",
  "postal_code": "...",
  "primary_contact_title": "Mr.",
  "primary_contact_first_name": "...",
  "primary_contact_last_name": "...",
  "primary_contact_email": "...",
  "primary_contact_country": "...",
  "primary_contact_mobile": "...",
  "primary_contact_passport": "<file>",
  "secondary_contact_title": "Mrs.",
  "secondary_contact_first_name": "...",
  "secondary_contact_last_name": "...",
  "secondary_contact_email": "...",
  "secondary_contact_country": "...",
  "secondary_contact_mobile": "...",
  "secondary_contact_passport": "<file>",
  "company_gov_registry_number": "...",
  "company_registration_certificate": "<file>",
  "agreed_to_receive_communications": true,
  "agreed_to_terms_and_conditions": true
}
```

**Response (201):** Unchanged — `message`, `user` (with `role: "competency_admin"`), `token`.

---

## 3. Login & Profile

### `POST /api/auth/login`

- No request changes.
- **Response:** `user.role` can be `"competency_admin"`. Use it the same way as `acc_admin` for routing and UI (e.g. redirect to ACC dashboard).

### `GET /api/auth/profile`

- **Response:** `user` may have `role: "competency_admin"`. Name is synced from the linked ACC (same as `acc_admin`).

---

## 4. Route Access (Competency Admin = ACC Admin)

Competency admins have access to the **same endpoints** as ACC admins:

| Route prefix | Middleware | Competency admin access |
|--------------|------------|-------------------------|
| **`/api/acc/*`** | `role:acc_admin,competency_admin` + `acc.active` | ✅ Full access (profile, dashboard, subscription, courses, certificate templates, codes, instructors, etc.) |
| **`/api/admin/*`** (shared) | `role:group_admin,acc_admin,competency_admin` | ✅ Same as ACC (e.g. category/subcategory template download and import) |

- Use the **same base URL and headers** as for ACC: e.g. `GET /api/acc/profile`, `GET /api/acc/dashboard`, etc., with the same Bearer token.
- **403** when ACC is not active (e.g. pending approval) — same behavior and message as for `acc_admin`.

---

## 5. Response Shapes

No new response fields. Only **`user.role`** gains a new possible value:

| Endpoint | Change |
|----------|--------|
| `POST /api/auth/register` | `user.role` can be `"competency_admin"`. |
| `POST /api/auth/login` | `user.role` can be `"competency_admin"`. |
| `GET /api/auth/profile` | `user.role` can be `"competency_admin"`. |

All other ACC-related responses (profile, dashboard, courses, etc.) are unchanged.

---

## 6. Frontend Checklist

- [ ] **Registration**
  - Add **Competency Admin** (or “Competency Body”) as a third option next to Training Center and ACC Admin.
  - When `role === 'competency_admin'` use the **same form and validation as ACC Admin** (same fields, same required files).
  - Send `role: "competency_admin"` in the registration request.

- [ ] **Login / routing**
  - After login (or when reading profile), if `user.role === 'competency_admin'`, route to the **same dashboard as ACC** (e.g. `/acc/dashboard` or your ACC app section).
  - Treat `competency_admin` like `acc_admin` in any role-based routing or permission checks (e.g. `['acc_admin', 'competency_admin'].includes(user.role)`).

- [ ] **Display**
  - Where you show “ACC Admin”, consider showing “Competency Admin” or “Competency Body” when `user.role === 'competency_admin'` (labels only; no API change).

- [ ] **Guards / permissions**
  - Any frontend guard that allows `acc_admin` for ACC routes should also allow `competency_admin` (same URLs and behavior).

---

## 7. Summary

| Topic | Detail |
|-------|--------|
| **New role** | `competency_admin` |
| **Registration** | Same request as `acc_admin`; set `role: "competency_admin"`. |
| **Login / profile** | `user.role` may be `"competency_admin"`; handle like `acc_admin`. |
| **API routes** | Same as ACC: `/api/acc/*` and shared `/api/admin/*` routes. |
| **Response changes** | Only `user.role` can be `"competency_admin"`; no other schema changes. |
