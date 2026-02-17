import json
import re

route_output = r"""
  GET|HEAD  admin/api-console admin.api_console › Admin\ApiConsoleController@…  
  POST      api/augmont/addresses ................ Api\AddressController@store  
  GET|HEAD  api/augmont/addresses/{uniqueId} ..... Api\AddressController@index  
  DELETE    api/augmont/addresses/{uniqueId}/{addressId} Api\AddressControlle…  
  GET|HEAD  api/augmont/balance/{uniqueId} .... Api\WithdrawController@balance  
  POST      api/augmont/banks ....................... Api\BankController@store  
  GET|HEAD  api/augmont/banks/validate-ifsc/{ifsc} Api\BankController@validat…  
  GET|HEAD  api/augmont/banks/{uniqueId} ............ Api\BankController@index  
  DELETE    api/augmont/banks/{uniqueId}/{bankId} . Api\BankController@destroy  
  POST      api/augmont/fd ............................ Api\FdController@store  
  POST      api/augmont/fd/pre-order ............... Api\FdController@preOrder  
  GET|HEAD  api/augmont/fd/schemes .................. Api\FdController@schemes  
  GET|HEAD  api/augmont/fd/terms ...................... Api\FdController@terms  
  GET|HEAD  api/augmont/fd/{uniqueId} ................. Api\FdController@index  
  GET|HEAD  api/augmont/fd/{uniqueId}/transactions Api\FdController@transacti…  
  GET|HEAD  api/augmont/fd/{uniqueId}/{fdId} ........... Api\FdController@show  
  POST      api/augmont/fd/{uniqueId}/{fdId}/close .... Api\FdController@close  
  GET|HEAD  api/augmont/fd/{uniqueId}/{fdId}/passbook Api\FdController@passbo…  
  POST      api/augmont/fd/{uniqueId}/{fdId}/pre-close-calc Api\FdController@…  
  GET|HEAD  api/augmont/invoices/buy/{transactionId} Api\InvoiceController@buy  
  GET|HEAD  api/augmont/invoices/redeem/{transactionId} Api\InvoiceController…  
  GET|HEAD  api/augmont/invoices/sell/{transactionId} Api\InvoiceController@s…  
  GET|HEAD  api/augmont/kyc/{uniqueId} ......... Api\AugmontKycController@show  
  PUT       api/augmont/kyc/{uniqueId} ....... Api\AugmontKycController@update  
  POST      api/augmont/orders ..................... Api\OrderController@store  
  GET|HEAD  api/augmont/orders/{uniqueId} .......... Api\OrderController@index  
  GET|HEAD  api/augmont/orders/{uniqueId}/{orderId} . Api\OrderController@show  
  GET|HEAD  api/augmont/products ................. Api\ProductController@index  
  GET|HEAD  api/augmont/products/{productId} ...... Api\ProductController@show  
  GET|HEAD  api/augmont/sip .......................... Api\SipController@index  
  POST      api/augmont/sip .......................... Api\SipController@store  
  GET|HEAD  api/augmont/sip/rates .................... Api\SipController@rates  
  PUT       api/augmont/sip/{id} .................... Api\SipController@update  
  POST      api/augmont/transfers ............... Api\TransferController@store  
  GET|HEAD  api/augmont/transfers/{uniqueId} .... Api\TransferController@index  
  GET|HEAD  api/augmont/transfers/{uniqueId}/{transferId} Api\TransferControl…  
  POST      api/augmont/users ................ Api\AugmontUserController@store  
  PUT       api/augmont/users/{uniqueId} .... Api\AugmontUserController@update  
  GET|HEAD  api/augmont/users/{uniqueId}/passbook Api\AugmontUserController@p…  
  PUT       api/augmont/withdraw ............... Api\WithdrawController@update  
  GET|HEAD  api/augmont/withdraw/{uniqueId} ...... Api\WithdrawController@info  
  POST      api/auth/logout ........................ Api\AuthController@logout  
  POST      api/auth/merchant-login ......... Api\AuthController@merchantLogin  
  POST      api/auth/register .................... Api\AuthController@register  
  POST      api/auth/send-email-otp .......... Api\AuthController@sendEmailOtp  
  POST      api/auth/verify-email-otp ...... Api\AuthController@verifyEmailOtp  
  GET|HEAD  api/banks ........................... Api\BankController@listLocal  
  POST      api/banks/add ........................ Api\BankController@addLocal  
  GET|HEAD  api/banks/validate-ifsc/{ifsc} ... Api\BankController@validateIfsc  
  POST      api/banks/verify ............ Api\BankController@verifyBankAccount  
  PUT       api/banks/{id} .................... Api\BankController@updateLocal  
  DELETE    api/banks/{id} .................... Api\BankController@deleteLocal  
  POST      api/gold/buy .......................... Api\GoldController@buyGold  
  GET|HEAD  api/gold/buy-info .................. Api\GoldController@getBuyInfo  
  POST      api/gold/buy-invoice ............ Api\GoldController@getBuyInvoice  
  GET|HEAD  api/gold/buy-list .................. Api\GoldController@getBuyList  
  GET|HEAD  api/gold/rates ....................... Api\GoldController@getRates  
  POST      api/gold/sell ........................ Api\GoldController@sellGold  
  GET|HEAD  api/gold/sell-info ................ Api\GoldController@getSellInfo  
  POST      api/gold/sell-invoice .......... Api\GoldController@getSellInvoice  
  GET|HEAD  api/gold/sell-list ................ Api\GoldController@getSellList  
  POST      api/gold/update-transaction-status Api\GoldController@updateTrans…  
  POST      api/kyc/aadhaar/generate-otp Api\KycController@aadhaarGenerateOtp   
  POST      api/kyc/aadhaar/verify-otp .... Api\KycController@aadhaarVerifyOtp  
  POST      api/kyc/status .................... Api\KycController@getKycStatus  
  POST      api/kyc/submit ....................... Api\KycController@submitKyc  
  POST      api/kyc/verify-pan ................... Api\KycController@verifyPan  
  GET|HEAD  api/master/banks ...................... Api\MasterController@banks  
  GET|HEAD  api/master/cities/{stateId} .......... Api\MasterController@cities  
  GET|HEAD  api/master/states .................... Api\MasterController@states  
  POST      api/payment/create-order ....... Api\PaymentController@createOrder  
  GET|HEAD  api/payment/order-status .... Api\PaymentController@getOrderStatus  
  GET|HEAD  api/rates/historical ............... Api\RateController@historical  
  GET|HEAD  api/rates/live ........................... Api\RateController@live  
  POST      api/resend-otp ....................... Api\OtpController@resendOtp  
  POST      api/send-otp ........................... Api\OtpController@sendOtp  
  GET|HEAD  api/user .........................................................  
  GET|HEAD  api/user/dashboard .................. Api\UserController@dashboard  
  GET|HEAD  api/user/profile ...................... Api\UserController@profile  
  POST      api/user/profile ................ Api\UserController@updateProfile  
  GET|HEAD  api/user/transactions ............ Api\UserController@transactions  
  POST      api/verify-otp ....................... Api\OtpController@verifyOtp  
  POST      api/wallet/add-money ............... Api\WalletController@addMoney  
  GET|HEAD  api/wallet/balance .................. Api\WalletController@balance  
  GET|HEAD  api/wallet/transactions ........ Api\WalletController@transactions  
  POST      api/wallet/verify-payment ..... Api\WalletController@verifyPayment  
  POST      api/wallet/withdraw ................ Api\WalletController@withdraw  
  POST      api/webhooks/augmont/fd-closed .... Api\WebhookController@fdClosed  
  POST      api/webhooks/augmont/fd-matured .. Api\WebhookController@fdMatured  
  POST      api/webhooks/augmont/fd-monthly-interest Api\WebhookController@fd…  
  POST      api/webhooks/augmont/user-create Api\WebhookController@augmontUse…  
  POST      api/webhooks/cashfree ...... Api\WebhookController@cashfreePayment  
  POST      api/webhooks/quickekyc-status Api\WebhookController@quickeKycStat…  
  POST      api/webhooks/transaction-status Api\WebhookController@transaction…  
"""

collection = {
    "info": {
        "name": "Freshrozgold API",
        "schema": "https://schema.getpostman.com/json/collection/v2.1.0/collection.json"
    },
    "variable": [
        {
            "key": "base_url",
            "value": "https://rozgold.in",
            "type": "string"
        }
    ],
    "item": []
}

def get_folder(path_parts, current_level):
    if not path_parts:
        return current_level
    
    part = path_parts[0]
    # Simple capitalization for folder names
    folder_name = part.replace("-", " ").title()
    
    # Try to find existing folder
    existing_folder = next((item for item in current_level if item.get("name") == folder_name and "item" in item), None)
    
    if not existing_folder:
        existing_folder = {
            "name": folder_name,
            "item": []
        }
        current_level.append(existing_folder)
        
    return get_folder(path_parts[1:], existing_folder["item"])

lines = route_output.strip().split('\n')
for line in lines:
    parts = re.split(r'\s+', line.strip())
    if len(parts) < 2:
        continue
        
    method = parts[0]
    if '|' in method:
        method = 'GET' # Simplify GET|HEAD to GET
    
    uri = parts[1]
    
    # Skip admin or non-api routes if any leaked (though filter was api)
    if not uri.startswith('api/'):
        continue
        
    # Remove 'api/' prefix for folder structure logic, but keep in url
    uri_clean = uri[4:] 
    uri_parts = uri_clean.split('/')
    
    # Last part is usually the action/resource or a param
    # We want to group by the segments before the last one if possible, or just the first few
    
    # Strategy: Group by first segment (e.g., 'augmont', 'auth', 'gold')
    # If 'augmont', then group by second segment too.
    
    group_parts = []
    
    if uri_parts[0] == 'augmont':
        if len(uri_parts) > 1:
            group_parts = ['Augmont', uri_parts[1]]
        else:
            group_parts = ['Augmont']
    elif uri_parts[0] == 'webhooks':
        group_parts = ['Webhooks']
    elif uri_parts[0] == 'kyc':
        group_parts = ['KYC']
    elif uri_parts[0] == 'wallet':
        group_parts = ['Wallet']
    elif uri_parts[0] == 'gold':
        group_parts = ['Gold']
    elif uri_parts[0] == 'auth':
        group_parts = ['Auth']
    elif uri_parts[0] == 'banks':
        group_parts = ['Banks']
    elif uri_parts[0] == 'master':
        group_parts = ['Master Data']
    elif uri_parts[0] == 'payment':
        group_parts = ['Payment']
    elif uri_parts[0] == 'rates':
        group_parts = ['Rates']
    elif uri_parts[0] == 'user':
        group_parts = ['User']
    else:
        group_parts = ['Other']
        
    # Get or create the folder structure
    current_folder = collection["item"]
    for folder_name in group_parts:
        folder_name = folder_name.replace("-", " ").title()
        existing = next((i for i in current_folder if i.get("name") == folder_name and "item" in i), None)
        if not existing:
            existing = {
                "name": folder_name,
                "item": []
            }
            current_folder.append(existing)
        current_folder = existing["item"]
    
    # Create the request item
    request_name = uri_parts[-1]
    if request_name.startswith('{'):
        # If it ends with a parameter, maybe take the preceding part + param
        if len(uri_parts) > 1:
            request_name = uri_parts[-2] + "/" + uri_parts[-1]
    
    # Better naming: split by camelCase controller action is hard from this output
    # but we can improve naming by replacing - with space
    request_name = request_name.replace('-', ' ').title()
    if request_name == "Api": request_name = uri # Fallback if emptyish
    
    # Construct URL with variable
    url_host = "{{base_url}}"
    url_path = uri.split('/')
    
    item = {
        "name": uri, # Using URI as name is clearest for now
        "request": {
            "method": method,
            "header": [
                {
                    "key": "Accept",
                    "value": "application/json",
                    "type": "text"
                },
                {
                    "key": "Content-Type",
                    "value": "application/json",
                    "type": "text"
                }
            ],
            "url": {
                "raw": url_host + "/" + uri,
                "host": [
                    "{{base_url}}"
                ],
                "path": url_path
            }
        },
        "response": []
    }
    
    current_folder.append(item)

print(json.dumps(collection, indent=4))
