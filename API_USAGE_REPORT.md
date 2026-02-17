# Rozgold API Usage Report

## 🟢 Currently Used APIs (Core Features)
These APIs are critical and currently implemented in the mobile app.

| Method | Endpoint | Description | Used In |
| :--- | :--- | :--- | :--- |
| `POST` | `/api/gold/buy` | Execute gold purchase | `GoldBuyScreen.js` |
| `POST` | `/api/gold/sell` | Execute gold sale | `GoldSellScreen.js` |
| `GET` | `/api/gold/rates` | Fetch live buy/sell rates | `DashboardScreen.js`, `GoldBuyScreen.js`, `GoldSellScreen.js` |
| `POST` | `/api/gold/update-transaction-status` | Update payment status after success | `GoldBuyScreen.js` |

---

## 🔴 Unused / Future APIs
These endpoints exist in `apiService.js` and the backend but are **not called** by any screen in the mobile app yet.

| Method | Endpoint | Description | Status |
| :--- | :--- | :--- | :--- |
| `GET` | `/api/gold/buy-info` | Pre-buy information/limits | Not connected |
| `POST` | `/api/gold/buy-invoice` | Generate PDF invoice for buy | Not connected |
| `GET` | `/api/gold/buy-list` | List of only buy transactions | Not connected (App uses generic history) |
| `GET` | `/api/gold/sell-info` | Pre-sell information/limits | Not connected |
| `POST` | `/api/gold/sell-invoice` | Generate PDF invoice for sell | Not connected |
| `GET` | `/api/gold/sell-list` | List of only sell transactions | Not connected (App uses generic history) |

## 📝 Recommendations
1. **Invoices:** Connect the `/api/gold/buy-invoice` and `/api/gold/sell-invoice` endpoints if you plan to add a "Download Invoice" button in the transaction history.
2. **Transaction Lists:** You can likely remove `/api/gold/buy-list` and `/api/gold/sell-list` if your main `getUserTransactions` (generic history) covers all needs.
