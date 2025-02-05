# Contributing to ArkVel

Thank you for your interest in contributing to **ArkVel**! 🚀 This open-source project aims to facilitate animal adoption in Europe through a modern and intuitive platform.

Follow these guidelines to contribute effectively and help the community grow! 🐾

---

## 🛠️ Getting Started

### 1️⃣ Fork & Clone the Repository

1. **Fork** the repository on GitHub
2. Clone the repository to your local environment:
   ```sh
   git clone https://github.com/your-username/arkvel.git
   cd arkvel
   ```

### 2️⃣ Set Up the Development Environment

1. Ensure you have **Docker** installed
2. Start the development containers:
   ```sh
   docker-compose up -d
   ```
3. Install dependencies:
   ```sh
   composer install
   npm install && npm run dev
   ```
4. Generate the `.env` file and configure the database:
   ```sh
   cp .env.example .env
   php artisan key:generate
   ```

---

## 🎯 How to Contribute

### ✅ Follow the Git Workflow

1. **Create a new branch** for each feature or fix:
   ```sh
   git checkout -b feature/your-feature-name
   ```
2. **Make clear and descriptive commits**:
   ```sh
   git commit -m "Added feature X for managing adoptions"
   ```
3. **Push your changes** to your fork:
   ```sh
   git push origin feature/your-feature-name
   ```
4. **Open a Pull Request (PR)** on GitHub

### 📌 Coding Guidelines

- Follow **Laravel** and **PSR-12** standards for PHP code
- Keep your code **readable** and **well-documented**
- Avoid unnecessary changes to configuration files
- Use **Tailwind CSS** for UI styling

---

## 🧪 Testing

Before submitting a PR, ensure your code is tested:

1. Run tests with PHPUnit:
   ```sh
   php artisan test
   ```
2. If you’ve modified the frontend, verify the UI behavior

---

## 📌 Tasks and Progress Tracking

Throughout development, an **updated list of tasks** will be maintained with their progress status. You can find this list in the **GitHub Projects section** or in the `TASKS.md` file, which will be updated periodically.

If you’d like to contribute to an open task, feel free to assign yourself or reach out for more details!

---

## 💬 Support and Discussions

- Open an **issue** if you find bugs or want to suggest improvements
- Follow the repository to stay updated on new developments

Thank you for your contribution! 🚀🐾
