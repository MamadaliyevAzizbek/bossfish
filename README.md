# Baliqchilik — 3 rolli tizim

## Rollar

1. **Egasi** — hamma ma’lumotni ko‘radi va o‘zgartiradi (yashirin kirish)
2. **Direktor** — faqat o‘z havzasini boshqaradi
3. **Hodim** — biriktirilgan havza bo‘yicha hisobot topshiradi

Bitta MySQL baza: kim o‘zgartirsa, hammaga ko‘rinadi.

## Kirish

| Kim | URL |
|-----|-----|
| Ochiq bosh sahifa | http://127.0.0.1:8000 |
| Direktor kirish / ro‘yxat | `/direktor/kirish` · `/direktor/royxat` |
| Hodim kirish / ro‘yxat | `/hodim/kirish` · `/hodim/royxat` |
| **Egasi (yashirin)** | `/egasi/kirish` — ochiq menyuda yo‘q |

### Test loginlar (seed)

| Rol | URL | Login | Parol |
|-----|-----|-------|-------|
| Egasi | `/egasi/kirish` | `ega@baliq.uz` | `Ega@2026!Strong` |
| Direktor | `/direktor/kirish` | `+998901234567` | `dir1234` |
| Hodim | `/hodim/kirish` | `+998907654321` | `work1234` |
| Venger | `/venger/kirish` | `hodim@vengercarp.uz` | `Venger@2026!Strong` |

Direktor qo‘shilganda **Standart** tarif avtomatik ochiladi — darhol login ishlaydi.

## Ega kabineti (1-qadam)

- Direktor qo‘shish
- Havza qo‘shish: **1. Nomi · 2. Viloyat · 3. Maydon**

## Stack

Laravel 11 + Vue 3 SPA + Laravel Mix + **MySQL** (`baliqchilik`)

## MySQL baza (XAMPP)

`.env` allaqachon MySQL ga sozlangan:

```
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=baliqchilik
DB_USERNAME=root
DB_PASSWORD=
```

1. XAMPP da MySQL ni yoqing  
2. Bazani yaratish / tiklash:

```bash
# yangi o‘rnatish (migratsiya)
php artisan migrate --seed

# yoki hozirgi bazani import qilish
mysql -h 127.0.0.1 -u root < database/sql/baliqchilik.sql
```

Dump fayllar: `database/sql/baliqchilik.sql` (ma’lumot bilan), `database/sql/baliqchilik_schema.sql` (faqat struktura).
