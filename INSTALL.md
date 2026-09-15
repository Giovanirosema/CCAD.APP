# Gid enstalasyon — CCAD (Gestion interne)

Gid sa a eksplike kijan pou enstale aplikasyon CCAD sou yon PC oswa yon sèvè nan biwo konpayi a. Chwazi seksyon ki koresponn ak sistèm ou an : **Windows** oswa **Linux**.

---

## OPSYON A — Windows (PC biwo, avèk XAMPP)

Se chwa ki pi senp pou yon PC biwo òdinè.

### 1. Enstale XAMPP
1. Telechaje XAMPP (ki gen Apache + PHP + MySQL/MariaDB ansanm) sou https://www.apachefriends.org/
2. Enstale l ak paramèt default yo (kite Apache, MySQL, ak phpMyAdmin koche).
3. Louvri **XAMPP Control Panel** epi klike **Start** sou **Apache** ak **MySQL**.

### 2. Kopye aplikasyon an
1. Dekonpresè (unzip) dosye `CCAD.APP.zip` la.
2. Kopye tout kontni li nan :
   ```
   C:\xampp\htdocs\ccad
   ```
   (kreye dosye `ccad` si li pa la, epi mete fichye yo ladan — `index.php`, `login.php`, `config\`, `sql\`, elatriye dwe dirèkteman anba `ccad\`, pa nan yon sou-dosye anplis.)

### 3. Kreye baz done a
1. Louvri **phpMyAdmin** : http://localhost/phpmyadmin
2. Klike **Nouvelle base de données**, non : `ccad_gestion`, kolasyon : `utf8mb4_unicode_ci` → **Créer**.
3. Klike sou baz `ccad_gestion` la, ale nan onglet **Importer**, chwazi fichye `sql\schema.sql` → **Exécuter**.
4. Refè menm bagay la ak fichye `sql\seed.sql` (sa a ajoute done demo + kont itilizatè yo).

   *(Altènativman, ou ka itilize onglet SQL nan phpMyAdmin epi kole kontni chak fichye.)*

### 4. Konfigire koneksyon baz done a
1. Nan dosye `C:\xampp\htdocs\ccad\config\`, kopye fichye `local.php.example` epi renome kopi a `local.php`.
2. Louvri `local.php` ak Notepad epi mete vrè valè yo :
   ```php
   putenv('CCAD_DB_HOST=127.0.0.1');
   putenv('CCAD_DB_NAME=ccad_gestion');
   putenv('CCAD_DB_USER=root');
   putenv('CCAD_DB_PASS=');
   ```
   *(Sou yon enstalasyon XAMPP default, itilizatè MySQL se `root` san mo pas — men se pi bon pou kreye yon itilizatè dedye, gade Opsyon B, etap 3, pou egzanp kòman.)*

### 5. Teste aplikasyon an
1. Louvri navigatè a epi ale sou : **http://localhost/ccad/login.php**
2. Konekte ak youn nan kont demo yo (mo pas pou tout moun : `CCAD-2026`) :

   | Idantifiant     | Wòl                           |
   |-----------------|--------------------------------|
   | j.deriphonse    | Administrateur                 |
   | e.baptichon     | Administrateur départemental    |
   | n.charles       | Agent de gestion                |
   | s.aubourg       | Caissier                        |
   | w.toussaint     | Agent de collecte               |

3. **Chanje mo pas demo yo touswit** (nan `parametres.php` → Itilizatè) anvan konpayi a itilize l pou vre.

---

## OPSYON B — Linux (Ubuntu/Debian, sèvè ki ap kouri tout tan)

Pi bon pou yon sèvè ki ap sèvi plizyè moun sou rezo lokal la san yo pa bezwen limen PC yon moun.

### 1. Enstale pakè yo
```bash
sudo apt update
sudo apt install -y php php-mysql mariadb-server
```

### 2. Kreye baz done a
```bash
sudo mysql -u root <<'SQL'
CREATE DATABASE ccad_gestion CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'ccad_app'@'127.0.0.1' IDENTIFIED BY 'CHANJE-MO-PAS-SA-A';
GRANT ALL PRIVILEGES ON ccad_gestion.* TO 'ccad_app'@'127.0.0.1';
FLUSH PRIVILEGES;
SQL

mysql -u root ccad_gestion < sql/schema.sql
mysql -u root ccad_gestion < sql/seed.sql
```

### 3. Konfigire koneksyon an
Opsyon 1 — variab anviwònman (pi bon pou yon sèvis systemd) :
```bash
export CCAD_DB_HOST=127.0.0.1
export CCAD_DB_NAME=ccad_gestion
export CCAD_DB_USER=ccad_app
export CCAD_DB_PASS=CHANJE-MO-PAS-SA-A
```

Opsyon 2 — fichye `config/local.php` (pi senp pou tès rapid) :
```bash
cp config/local.php.example config/local.php
nano config/local.php   # mete vrè valè yo
```

### 4. Lanse aplikasyon an
Pou tès rapid (sèvè entegre PHP) :
```bash
php -S 0.0.0.0:8000
```
Ale sou `http://<adrès-IP-PC-a>:8000/login.php` apati nenpòt lòt PC sou menm rezo a.

Pou yon deplwaman pèmanan, konfigire Apache oswa Nginx pou pwente `document root` la sou dosye rasin CCAD a (kote `index.php` ye a), aktive HTTPS, epi kreye yon sèvis `systemd` si w ap itilize `php-fpm`.

### 5. Teste ak chanje mo pas demo yo
Menm etap ak Windows la, seksyon 5 pi wo.

---

## Enfòmasyon sekirite enpòtan anvan itilizasyon reyèl

- **Chanje tout mo pas kont demo yo** (`CCAD-2026`) touswit.
- Pa kite `config/local.php` (oswa variab anviwònman ki gen mo pas) nan yon kopi ki pataje piblikman — li deja egzante nan git (`.gitignore`).
- Sou Windows/XAMPP, pa itilize `root` san mo pas an pwodiksyon — kreye yon itilizatè MySQL dedye ak dwa limite sou `ccad_gestion` sèlman (menm fason ak egzanp Linux la, etap 2).
- Aktive HTTPS anvan itilizatè yo konekte soti deyò rezo lokal biwo a.
- Backup regilye : ekspòte `ccad_gestion` (phpMyAdmin → Exporter, oswa `mysqldump`) epi konsève dosye `assets/uploads/` (foto/dokiman asire yo).
