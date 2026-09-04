<?php
declare(strict_types=1);

// Update these values after creating the MySQL database in GoDaddy cPanel.
const DB_HOST = 'localhost';
const DB_NAME = 'clientele';
const DB_USER = 'clientele_admin';
const DB_PASS = 'Abcd@2020206';

const APP_NAME = 'Clientele Admin';
const BASE_URL = '';
const APP_TIMEZONE = 'Asia/Kolkata';
const DB_TIMEZONE_OFFSET = '+05:30';

// Replace this with your own random 32+ character value before uploading.
const APP_SECRET = 'a9f4c2d8e71b46a39f0c82e15d7b6a91f3e8c4b29d0a65f7';

const CLIENT_LOGO_UPLOAD_DIR = __DIR__ . '/../uploads/client-logos';
const CLIENT_LOGO_PUBLIC_PATH = 'uploads/client-logos';
const MAX_LOGO_UPLOAD_BYTES = 2097152;

// SSO handoff from the master application. Leave empty to allow any valid email
// sent by the trusted master app, or add domains like ['eduriser.com'].
const SSO_ALLOWED_EMAIL_DOMAINS = ['eduriser.com'];

date_default_timezone_set(APP_TIMEZONE);
