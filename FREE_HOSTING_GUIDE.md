# Fitrova Free Backend Hosting Guide

This guide provides step-by-step instructions to host the Fitrova backend (PHP API, MySQL database, and Python Flask AI service) **completely for free**. 

Since your project is already fully containerized with Docker, you have two excellent free options depending on your preference.

---

## Table of Contents
1. [Option A: Oracle Cloud Infrastructure (Recommended - All-in-One VPS)](#option-a-oracle-cloud-infrastructure-recommended)
2. [Option B: Decoupled Free Services (No Server Management Required)](#option-b-decoupled-free-services-no-server)
3. [Post-Deployment Configuration Checklist](#post-deployment-configuration-checklist)

---

## Option A: Oracle Cloud Infrastructure (Recommended)
Oracle Cloud offers an **Always Free** tier that includes a virtual private server (VPS) with up to **24 GB RAM and 4 ARM CPUs**. This is more than enough resources to run your database, web server, and AI video processing service together.

### Step 1: Sign Up for Oracle Cloud Always Free
1. Go to [Oracle Cloud Free Tier](https://www.oracle.com/cloud/free/).
2. Click **Start for free** and complete the sign-up process.
   > [!IMPORTANT]
   > Oracle requires a valid credit/debit card for verification. They will make a temporary authorization hold (which is immediately reversed). Some virtual or prepaid cards may be rejected.
3. Select your **Home Region** closest to your users (e.g., London, Frankfurt, or Johannesburg).

### Step 2: Create your Compute Instance
1. Log into your Oracle Cloud Console.
2. Go to **Compute** > **Instances** > **Create Instance**.
3. Configure the instance:
   * **Name**: `fitrova-backend-prod`
   * **Image**: `Ubuntu 22.04 LTS` (Default)
   * **Shape**: Click *Change Shape* -> select **Ampere (ARM-based processor)** -> Allocate **2 OCPUs** and **12 GB RAM** (or up to 4 OCPUs and 24 GB RAM).
   * **Networking**: Choose/create the default Virtual Cloud Network (VCN) and assign a public IP address.
   * **SSH Keys**: Generate and download the private SSH key file (`.key`). **Keep this file safe!**
4. Click **Create** and wait for the status to turn green (Active).

### Step 3: Configure Network Ports (Ingress Rules)
By default, Oracle blocks external traffic. You need to open ports 80 (HTTP) and 443 (HTTPS):
1. In your Instance details, click on your **Primary VNIC** subnet link.
2. Click on your VCN's **Default Security List**.
3. Click **Add Ingress Rules**:
   * **Source CIDR**: `0.0.0.0/0`
   * **IP Protocol**: `TCP`
   * **Destination Port Range**: `80,443`
   * **Description**: `Allow HTTP and HTTPS traffic`
4. Click **Add Ingress Rules**.

### Step 4: Access your Server and Install Docker
Open your terminal (macOS/Linux) or PowerShell (Windows) and connect to the VPS:
```bash
# Secure your SSH key
chmod 400 path/to/your-key.key

# SSH into the server (replace your-server-ip with the Public IP from Oracle)
ssh -i path/to/your-key.key ubuntu@your-server-ip
```

Once logged in, update your server and install Docker + Docker Compose:
```bash
# Update packages
sudo apt update && sudo apt upgrade -y

# Install Docker
sudo apt install docker.io -y
sudo systemctl enable --now docker

# Install Docker Compose
sudo curl -L "https://github.com/docker/compose/releases/latest/download/docker-compose-$(uname -s)-$(uname -m)" -o /usr/local/bin/docker-compose
sudo chmod +x /usr/local/bin/docker-compose

# Add your user to the docker group
sudo usermod -aG docker $USER
newgrp docker
```

### Step 5: Deploy the Fitrova Backend
1. Clone your Git repository onto the VPS:
   ```bash
   git clone <your-repository-url> fitrova
   cd fitrova
   ```
2. Create and configure your production environment variables:
   ```bash
   cp backend/.env.example backend/.env.production
   nano backend/.env.production
   ```
   Update the following keys in `backend/.env.production`:
   * `DB_HOST=mysql` (Leave as is; Docker uses internal DNS)
   * `DB_NAME=fitrova_db`
   * `DB_USER=fitrova_user`
   * `DB_PASSWORD=your_secure_db_password`
   * `PAYSTACK_SECRET_KEY=sk_live_your_key` (Production keys)
   * `SMTP_USER` and `SMTP_PASS` (For verification emails)
   * `GEMINI_API_KEY=your_gemini_api_key`

3. Deploy using the script:
   ```bash
   chmod +x deploy.sh
   ./deploy.sh production
   ```
4. Verify your services are running:
   ```bash
   docker-compose -f docker/docker-compose.yml ps
   ```

---

## Option B: Decoupled Free Services (No Server Management)
If you don't want to configure an Ubuntu server, you can link together separate free hosting platforms. 

### Step 1: Set up a Free MySQL Database
Since Render's free database expires after 30 days, host your MySQL database on a permanent free tier database provider:
1. Go to [Aiven.io](https://aiven.io/) and create a free account.
2. Create a new service: Choose **MySQL** -> Select **Free Plan** (available in AWS regions like Frankfurt or US East).
3. Once the database is ready, copy your connection details:
   * **Host** (e.g., `mysql-xxx.aivencloud.com`)
   * **Port** (e.g., `12345`)
   * **User** (e.g., `avnadmin`)
   * **Password**
   * **Database Name** (e.g., `defaultdb`)

### Step 2: Deploy the Python AI Service to Hugging Face Spaces
Since video analysis takes significant memory, deploy the Python service to Hugging Face, which offers **16 GB RAM** for free.
1. Sign up/log in to [Hugging Face](https://huggingface.co/).
2. Click your profile icon and select **New Space**.
3. Configure the Space:
   * **Space Name**: `fitrova-ai-service`
   * **SDK**: Select **Docker** (Blank template).
   * **Visibility**: **Public** (You can secure it via token/IP limits in your Flask code, or keep it public as it only listens to API requests).
4. Hugging Face will generate a git repository URL for your Space. Clone it locally, copy the files from your `ai-service` folder into it, and push to deploy:
   ```bash
   git clone https://huggingface.co/spaces/your-username/fitrova-ai-service
   # Copy all files from Fitrova/ai-service into this directory
   git add .
   git commit -m "Deploy AI service"
   git push
   ```
5. In your Hugging Face Space settings, add your environment variables (`GEMINI_API_KEY`, etc.).
6. Once built, Hugging Face will provide a URL (e.g., `https://your-username-fitrova-ai-service.hf.space`). This is your `AI_SERVICE_URL`.

### Step 3: Deploy the PHP Backend to Render.com
1. Sign up/log in to [Render](https://render.com/).
2. Click **New** > **Web Service**.
3. Connect your GitHub repository.
4. Set the **Root Directory** to `backend`.
5. Set the **Runtime** to `Docker`.
6. Select the **Free** instance type (512MB RAM, 0.1 CPU).
7. Under **Environment**, add your variables:
   * `DB_HOST` = (Aiven Host)
   * `DB_PORT` = (Aiven Port)
   * `DB_USER` = (Aiven User)
   * `DB_PASS` = (Aiven Password)
   * `DB_NAME` = `defaultdb`
   * `AI_SERVICE_URL` = `https://your-username-fitrova-ai-service.hf.space/api/analyze-youtube` (Your HF Space endpoint)
   * `PAYSTACK_SECRET_KEY` = `sk_live_xxxx`
8. Click **Deploy Web Service**. Render will build the `backend/Dockerfile` and give you a URL (e.g., `https://fitrova-backend.onrender.com`).

---

## Post-Deployment Configuration Checklist

### 1. Update your Mobile App (Frontend)
Open `frontend/src/config/index.ts` (or your endpoint config file) and replace the local LAN IP address with your production URL:
```typescript
// Replace:
// export const API_BASE_URL = 'http://192.168.x.x/Fitrova/backend';

// With your Render or VPS production URL:
export const API_BASE_URL = 'https://fitrova-backend.onrender.com';
```

### 2. Update Paystack Webhooks
1. Log in to your **Paystack Dashboard**.
2. Go to **Settings** > **API Keys & Webhooks**.
3. Set your webhook URL to point to your new production endpoint:
   `https://your-production-domain.com/app/controllers/payments/paystack_webhook.php`

### 3. Turn on SSL Verification (Security)
In development, you may have disabled SSL verification to bypass local certificates. In production, ensure all cURL requests verify SSL.
Open your payment controllers (e.g., `paystack_initialize.php`, `paystack_verify.php`) and make sure SSL verification is set to `true`:
```php
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
```
