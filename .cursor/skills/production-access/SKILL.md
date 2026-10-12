---
name: production-access
description: Access AdventurKSA production (admin.adventurksa.com) over SSH via the local forge alias, locate the app root, and query the database with Laravel tinker. Use when connecting to production, inspecting live data, running artisan tinker, or working on admin.adventurksa.com.
---

# Production Access

1. The website admin is admin.adventurksa.com.
2. To access the production, use local alias "forge".
3. To find the root folder, do cd /admin.adventurksa.com
4. Can access db via tinker.

## Connect

```bash
ssh forge
cd /admin.adventurksa.com
```

## Database

```bash
php artisan tinker
```
