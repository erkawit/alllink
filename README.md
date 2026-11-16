# UDTC-Portal — ศูนย์กลางให้บริการระบบงานต่างๆ

ระบบรวบรวมลิงก์ **เว็บไซต์ · ระบบงาน/โปรแกรม · คู่มือ/เอกสาร** ที่เจ้าหน้าที่ศาลจังหวัดอุดรธานีใช้งานเป็นประจำ ไว้ในหน้าเดียว
ค้นหาได้เร็ว ปักหมุดรายการโปรดได้ และผู้ดูแลระบบจัดการรายการได้เองผ่านหน้าเว็บ

| | |
|---|---|
| ภาษา / Runtime | PHP 8.3 (Vercel Serverless Functions ผ่าน `vercel-php@0.7.4`) |
| ฐานข้อมูล | [Neon](https://neon.tech) — Serverless PostgreSQL |
| โฮสต์ | [Vercel](https://vercel.com) (ภูมิภาค `sin1` สิงคโปร์) |
| หน้าบ้าน | Tailwind CSS · jQuery DataTables 2 · SweetAlert2 · Font Awesome 6 · ฟอนต์ IBM Plex Sans Thai |
| Repository | <https://github.com/erkawit/alllink> |

---

## 1. ความสามารถของระบบ

- **หน้าแรก** แสดงรายการทั้งหมดในตาราง ค้นหา/เรียงลำดับ/แบ่งหน้าได้ พร้อมการ์ดสรุปจำนวนแยกตามประเภท (กดเพื่อกรอง)
- **3 ประเภทรายการ** — เว็บไซต์ (`website`), ระบบงาน/โปรแกรม (`system`), คู่มือ/เอกสาร (`document`)
- **คู่มือ/เอกสาร** แนบไฟล์ได้ (PDF, Word, Excel, PowerPoint, รูปภาพ, zip ฯลฯ) เปิดดู PDF/รูปในเบราว์เซอร์ได้ทันที ดูรายชื่อไฟล์ใน zip และตัวอย่างไฟล์ txt/csv ได้
- **รายการโปรด (Favorite)** กดดาว ⭐ แล้วรายการจะขึ้นไปอยู่บนสุด
  - ไม่ล็อกอิน: เก็บใน `localStorage` ของเบราว์เซอร์
  - ล็อกอิน: รวมรายการโปรดในเครื่องเข้าบัญชีอัตโนมัติ แล้วบันทึกทั้งในบัญชีและในเครื่อง
- **ผู้ดูแลระบบ** เพิ่ม/แก้ไข/ลบรายการ, เปิด-ปิดการแสดงผลรายการหรือไฟล์แนบ, จัดการบัญชีผู้ใช้
- ความปลอดภัย: CSRF token ทุกคำขอ API, รหัสผ่านเข้ารหัสด้วย `password_hash` (bcrypt), session เก็บในฐานข้อมูล, ไฟล์แนบเปิดผ่าน `file.php` ที่ตรวจสิทธิ์ก่อนเสมอ

### สิทธิ์การใช้งาน

| สิทธิ์ | ทำได้ |
|---|---|
| ผู้ใช้ทั่วไป (ไม่ล็อกอิน) | ดูรายการที่เปิดแสดงผล เปิดลิงก์/ไฟล์ กดดาว (เก็บในเครื่อง) |
| เจ้าหน้าที่ (`staff`) | เหมือนผู้ใช้ทั่วไป + รายการโปรดเก็บในบัญชี + เปลี่ยนรหัสผ่านตนเอง |
| ผู้ดูแลระบบ (`admin`) | เหมือนเจ้าหน้าที่ + เพิ่ม/แก้ไข/ลบรายการ, ซ่อน/แสดง, จัดการผู้ใช้ |

---

## 2. สถาปัตยกรรม

```
 เบราว์เซอร์ ──► Vercel (sin1)
                 ├─ /assets/*            ไฟล์ static (CSS/JS)
                 └─ /, *.php  ──►  PHP 8.3 Serverless Function (api/*.php)
                                         │  PDO pgsql (SSL)
                                         ▼
                                 Neon PostgreSQL
                                 ├─ users, links, favorites
                                 ├─ link_files  (เนื้อไฟล์แนบ BYTEA)
                                 └─ sessions    (PHP session)
```

เหตุผลของการออกแบบสำหรับ Vercel:

- **Serverless ไม่มีดิสก์ถาวร** → ไฟล์แนบที่อัปโหลดใหม่เก็บในตาราง `link_files` (BYTEA) แทนโฟลเดอร์ `uploads/`
- **แต่ละคำขออาจรันคนละเครื่อง** → PHP session เก็บในตาราง `sessions` (คลาส `DbSessionHandler`)
- **Vercel จำกัดขนาด request ~4.5 MB** → ขนาดไฟล์แนบสูงสุดตั้งไว้ 4 MB (ปรับด้วย env `MAX_UPLOAD_MB`)
- ไฟล์คู่มือ PDF เดิมจากระบบ XAMPP อยู่ใน `api/_files/` (อ่านอย่างเดียว) — `file.php` จะหาไฟล์ในฐานข้อมูลก่อน ถ้าไม่พบจึงอ่านจากโฟลเดอร์นี้
- URL เดิม (`index.php`, `users.php`, `api.php`, `file.php`) ยังใช้ได้เหมือนเดิม — `vercel.json` route ไปยังไฟล์ใน `api/`

---

## 3. โครงสร้างไฟล์

```
alllink/
├── api/                        ← PHP ทั้งหมด (Vercel Function)
│   ├── index.php               หน้าแรก (ตาราง DataTables)
│   ├── users.php               จัดการผู้ใช้ (admin)
│   ├── api.php                 API ทั้งหมด (AJAX POST + CSRF)
│   ├── file.php                เปิด/ดาวน์โหลดไฟล์แนบ
│   ├── install.php             สร้างตาราง + ข้อมูลเริ่มต้นบน Neon
│   ├── php.ini                 ค่า PHP บน Vercel (ขนาดอัปโหลด, timezone)
│   ├── _lib/
│   │   ├── config.php          ตั้งค่าระบบ, เชื่อมต่อ Neon, DB session
│   │   ├── header.php          ส่วนหัวหน้าเว็บ
│   │   └── footer.php          ส่วนท้ายหน้าเว็บ
│   └── _files/                 ไฟล์คู่มือ PDF เดิม (อ่านอย่างเดียว)
├── assets/                     app.css, common.js, app.js, users.js
├── database/
│   ├── neon/
│   │   ├── schema.sql          โครงสร้างตาราง PostgreSQL (รันซ้ำได้)
│   │   └── seed.sql            บัญชี admin + ลิงก์ 99 รายการจากระบบเดิม
│   └── mysql-legacy/           สคริปต์ MySQL เดิม (เก็บไว้อ้างอิง)
├── router.php                  ใช้ทดสอบบนเครื่อง (php -S)
├── vercel.json                 ตั้งค่า runtime / region / routes
├── .env.example                ตัวอย่างค่า DATABASE_URL
└── README.md
```

### ตารางในฐานข้อมูล

| ตาราง | คำอธิบาย |
|---|---|
| `users` | บัญชีผู้ใช้ (`admin` / `staff`), `is_active`, `last_login` — ชื่อผู้ใช้ไม่สนตัวพิมพ์เล็ก/ใหญ่ |
| `links` | รายการลิงก์/ระบบ/เอกสาร, สถานะแสดงผล, ข้อมูลไฟล์แนบ (ชื่อ, ชนิด, ขนาด) |
| `link_files` | เนื้อไฟล์แนบ (BYTEA) 1 ไฟล์ต่อ 1 รายการ — ลบตามอัตโนมัติเมื่อลบรายการ |
| `favorites` | รายการโปรดของผู้ใช้แต่ละคน |
| `sessions` | PHP session (หมดอายุ 8 ชั่วโมง) |

---

## 4. ที่มาของข้อมูล (ไฟล์ฐานข้อมูล 3 ไฟล์)

| ไฟล์เดิม | ใช้ทำอะไร | ผลลัพธ์ในระบบใหม่ |
|---|---|---|
| `database.sql` | โครงสร้าง MySQL ของ UDTC-Portal รุ่นใหม่ (users, links, favorites) | แปลงเป็น PostgreSQL → `database/neon/schema.sql` (+ ตาราง `link_files`, `sessions`) |
| `import_links.sql` | ลิงก์ 99 รายการที่แปลงจากระบบเดิมแล้ว (ตัดลิงก์ซ้ำ 12 รายการ) | `database/neon/seed.sql` พร้อมขนาดไฟล์คู่มือ PDF จริง |
| `alllink-16-11-2569.sql` | ฐานข้อมูลระบบเดิมทั้งหมด (app_list, group_name, all_user ฯลฯ) | ใช้เป็นต้นทางของลิงก์ — **ไม่นำขึ้น git** เพราะตาราง `all_user` มีชื่อผู้ใช้/รหัสผ่านของระบบต่างๆ แบบข้อความธรรมดา |

หมายเหตุ: รายการ "โปรแกรมข้อมูลปฏิทินรายงานผลการปฏิบัติงาน…" เดิมอ้างถึงไฟล์ `8a01c4343408a902f8b773c8daefa1f4.pdf` ซึ่งไม่มีอยู่ในโฟลเดอร์ `files/` แล้ว จึงเก็บไว้เฉพาะลิงก์ (แนบไฟล์ใหม่ได้จากหน้าแก้ไข)

---

## 5. ติดตั้งและ Deploy

### 5.1 สร้างฐานข้อมูล Neon

**ทางที่ 1 (แนะนำ) — สร้างผ่าน Vercel**
1. Vercel Dashboard → โปรเจกต์ → แท็บ **Storage** → **Create Database** → เลือก **Neon**
2. เลือก Region **Singapore (aws-ap-southeast-1)** ให้ใกล้กับ Function (`sin1`)
3. กด Connect กับโปรเจกต์ — Vercel จะตั้ง `DATABASE_URL` / `POSTGRES_URL` ให้อัตโนมัติ

**ทางที่ 2 — สร้างที่ neon.tech เอง**
1. <https://console.neon.tech> → New Project → Region **AWS Asia Pacific (Singapore)**
2. กด **Connect** → เลือก **Pooled connection** → คัดลอก connection string
3. Vercel → Project → **Settings → Environment Variables** → เพิ่ม `DATABASE_URL` = ค่าที่คัดลอก (ติ๊ก Production / Preview / Development)

### 5.2 Deploy ขึ้น Vercel

1. <https://vercel.com/new> → **Import Git Repository** → เลือก `erkawit/alllink`
2. Framework Preset: **Other** (ไม่ต้องตั้ง Build Command / Output Directory)
3. ใส่ Environment Variable `DATABASE_URL` (ถ้ายังไม่ได้เชื่อม Neon ผ่าน Storage) แล้วกด **Deploy**
4. ทุกครั้งที่ `git push` เข้า branch `main` Vercel จะ deploy ใหม่อัตโนมัติ

> ถ้าเพิ่ม/แก้ Environment Variable ภายหลัง ต้องกด **Redeploy** หนึ่งครั้งจึงจะมีผล

### 5.3 สร้างตารางและข้อมูลเริ่มต้น

เลือกทางใดทางหนึ่ง:

- **เปิด `https://<โดเมนของคุณ>.vercel.app/install.php`** — ระบบจะรัน `schema.sql` + `seed.sql` ให้
  (เมื่อมีผู้ใช้ในระบบแล้ว หน้านี้จะใช้ได้เฉพาะผู้ดูแลระบบที่ล็อกอินอยู่)
- หรือเปิด **Neon Console → SQL Editor** แล้ววางเนื้อหา `database/neon/schema.sql` ตามด้วย `database/neon/seed.sql` กด Run

### 5.4 เข้าใช้งานครั้งแรก

1. เข้าเว็บ → **เข้าสู่ระบบ** ด้วย `admin` / `admin1234`
2. **เปลี่ยนรหัสผ่านทันที** (ปุ่ม "เปลี่ยนรหัสผ่าน" มุมขวาบน)
3. เพิ่มบัญชีเจ้าหน้าที่ได้ที่เมนู **จัดการผู้ใช้**

---

## 6. ทดสอบบนเครื่อง (ไม่บังคับ)

ต้องมี PHP 8.1+ ที่เปิด extension `pdo_pgsql`

```bash
cp .env.example .env          # ใส่ DATABASE_URL ของ Neon (แนะนำแยก branch dev ใน Neon)
php -S localhost:8000 router.php
# เปิด http://localhost:8000/install.php ครั้งแรก แล้วเข้า http://localhost:8000/
```

---

## 7. Environment Variables

| ชื่อ | จำเป็น | คำอธิบาย |
|---|---|---|
| `DATABASE_URL` | ✅ | connection string ของ Neon (รองรับ `POSTGRES_URL` ที่ Vercel ตั้งให้ด้วย) |
| `MAX_UPLOAD_MB` | — | ขนาดไฟล์แนบสูงสุด (MB) ค่าเริ่มต้น `4` — บน Vercel ไม่ควรเกิน 4 |

---

## 8. API (`api.php?action=…`)

ทุกคำขอเป็น `POST` แบบ `multipart/form-data` และต้องส่ง `csrf` (ได้จาก `window.UDPT.csrf` ในหน้าเว็บ)

| action | สิทธิ์ | หน้าที่ |
|---|---|---|
| `login`, `logout`, `change_password` | ทุกคน / ล็อกอิน | เข้า-ออกระบบ, เปลี่ยนรหัสผ่าน |
| `links` | ทุกคน | รายการทั้งหมด (+ รายการโปรดเมื่อล็อกอิน) |
| `link_get`, `link_save`, `link_delete`, `link_toggle` | admin | จัดการรายการ / อัปโหลดไฟล์ / ซ่อน-แสดง |
| `file_info` | ทุกคน | รายชื่อไฟล์ใน zip, ตัวอย่าง txt/csv |
| `fav_set` | ล็อกอิน | เพิ่ม/ลบรายการโปรด |
| `users`, `user_save`, `user_delete` | admin | จัดการผู้ใช้ |

---

## 9. ข้อควรทราบ

- ระบบงานภายในหลายรายการใช้ที่อยู่ในเครือข่ายศาล (`10.x.x.x`, `*.coj.intra`) — เว็บบน Vercel แสดงลิงก์ได้ แต่ **จะเปิดระบบนั้นได้เฉพาะเครื่องที่อยู่ในเครือข่ายภายใน/VPN** เหมือนเดิม
- Neon แพ็กเกจฟรีมีพื้นที่ 0.5 GB — ไฟล์แนบเก็บในฐานข้อมูล จึงควรแนบเฉพาะคู่มือที่จำเป็น และใช้ลิงก์ (เช่น Google Drive) สำหรับไฟล์ขนาดใหญ่
- Neon แพ็กเกจฟรีจะพักฐานข้อมูลเมื่อไม่มีการใช้งาน คำขอแรกหลังพักอาจช้าประมาณ 1–2 วินาที
- อย่า commit ไฟล์ `.env` หรือไฟล์สำรองฐานข้อมูลเดิม (`.gitignore` กันไว้ให้แล้ว)

---

© ศาลจังหวัดอุดรธานี · UDTC-Portal v2.0.0
