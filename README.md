# Cryptomus-Paymenter

## Cryptomus Paymenter Extension

This is an extension for **Paymenter**, allowing merchants to integrate **Cryptomus** as a payment gateway.  
The extension enables customers to pay using **cryptocurrencies** while automating **payment status updates**.

---

## 🚀 Features

✅ **Secure payment processing** via Cryptomus  
✅ **Automatic payment status updates** via webhooks  
✅ **Supports multiple cryptocurrencies** 
✅ **Implements signature verification** for security  
✅ **Works with Paymenter v0.9**  

---

## 📌 Installation

### 1️⃣ Download the Extension  

Download this extension by going to **Browse Extensions** in your **Admin panel**.

### 2️⃣ Configure Paymenter  

Navigate to **Admin Panel → Settings → Extension Settings**, then:  
1. **Enable** the Cryptomus payment gateway  
2. Enter your **API Key** and **Merchant ID** (found in your Cryptomus account)  
3. Set the **currency** (e.g., `IDR`, `EUR`, `GBP`, default: `USD`)  

### 3️⃣ Setup Webhook  

1. The default **webhook URL** is:  

https://yourdomain.com/extensions/cryptomus/webhook

2. *(Optional)* Ensure you **whitelist Cryptomus IP**: `91.227.144.54`  

---

## 🛠️ Configuration Options  

| Option       | Description                         | Required |
|-------------|-------------------------------------|----------|
| `api_key`   | Your **Cryptomus API Key**         | ✅        |
| `merchant_id` | Your **Merchant ID** from Cryptomus | ✅        |
| `currency`  | Default currency (e.g. `IDR`, `EUR`, `GBP` default:`USD`)    | ✅        |

---

## 🔄 Webhook Handling  

Cryptomus sends **webhook notifications** when **payment status** changes. This extension:  
- ✅ **Verifies webhook signatures** using:  

```
md5(base64_encode(json_encode($data, JSON_UNESCAPED_UNICODE)) . $api_key)
```

  •	✅ Processes payment statuses:
  •	🟢 paid → Marks invoice as paid
  •	🔴 cancel, failed, expired → Logs            failure but does not complete             payment

## 💡 Troubleshooting

1️⃣ Webhook signature mismatch?
	•	Ensure your API Key is correct
	•	Check if Cryptomus webhook sends escaped JSON data

2️⃣ Payment not marked as completed?
	•	Check Paymenter logs:

storage/logs/laravel.log

  •	Verify webhook requests in Cryptomus Dashboard → Logs

3️⃣ Still having issues?
	•	Open a GitHub Issue or join Paymenter Discord

## 📝 License

This project is licensed under the MIT License.

## 👨‍💻 Author

Developed by 0xricoard | servermikro.com
