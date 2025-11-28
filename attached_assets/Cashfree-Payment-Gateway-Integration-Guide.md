Cashfree Payment Gateway Integration Guide
Updated: Oct 31, 2025
Purpose
The purpose of this document is to lay down the payment integration with Cashfree that is required for a merchant or Solution Partner that has setup a chatbot using WhatsApp Business APIs and needs to receive payments from WhatsApp users.
This document covers the set of APIs that need to be integrated and how the integration works in tandem with the WhatsApp Business API integration. For additional details regarding Cashfree payment integration, please refer to the Cashfree documentation.
Where this fits into the entire flow in terms of integration to the WA P2M product : The following document covers the requests, responses in red in the flow diagram below. Image
Cashfree Payment Integration
Setup
Obtain credentials (client id and secret) from Cashfree dashboard after setting up the account.
Obtain the merchant vpa, mcc and purpose code from Cashfree. This will be used to set up the payment configuration on WhatsApp. Multiple vpas are supported and hence multiple payment configurations can be set up. Each payment configuration should have one VPA.
Create order
This creates the order at Cashfree end.
Reference doc
Request
curl --request POST \
     --url https://sandbox.cashfree.com/pg/orders \ // Production URL : https://api.cashfree.com/pg/orders
     --header 'accept: application/json' \
     --header 'content-type: application/json' \
     --header 'x-api-version: 2022-09-01' \
     --header 'x-client-id: 26268833355ef02b8ff299390c886262' \
     --header 'x-client-secret: 1708cc38a3c1c3c2512d79b3530dc5cc65ad2fde' \
     --data '
    {
     "customer_details": {
          "customer_id": "7112AAA812234",
          "customer_email": "john@cashfree.com",
          "customer_phone": "9908734801",
          "customer_bank_account_number": "1518121112",
          "customer_bank_ifsc": "CITI0000001",
          "customer_bank_code": 3333
     },
     "order_meta": {
          "notify_url": "https://b8af79f41056.eu.ngrok.io/webhook.php", // Notification URL where status notifications sent - can be different for different merchants
          "payment_methods": "upi"
     },
      "order_tags": {
          "channel": "WhatsApp" // Custom tag
     },
     "order_id": "order02",
     "order_amount": 200.5,
     "order_currency": "INR",
     "order_expiry_time": "2022-12-29T00:00:00Z",
     "order_note": "Test order"
    }

Response
{
  "cf_order_id": 3401407,
  "created_at": "2022-12-26T14:11:07+05:30",
  "customer_details": {
    "customer_id": "7112AAA812234",
    "customer_name": null,
    "customer_email": "john@cashfree.com",
    "customer_phone": "9908734801"
  },
  "entity": "order",
  "order_amount": 200.5,
  "order_currency": "INR",
  "order_expiry_time": "2022-12-29T05:30:00+05:30",
  "order_id": "order02",
  "order_meta": {
    "return_url": null,
    "notify_url": "https://b8af79f41056.eu.ngrok.io/webhook.php",
    "payment_methods": "upi"
  },
  "order_note": "Test order",
  "order_splits": [],
  "order_status": "ACTIVE",
  "order_tags": {
    "channel": "WhatsApp" // Custom tag
  },
  "payment_session_id": "session_364o8HjN0-gc6n_n4EBEPOXriJUJvCeVIdy9u8ihOhwvpNg9F1wMorWmkVxUR90kTe473bpbotNxyZ6Fze8M0w42_BpTxoEWsbBR21y7i0nh",
  "payments": {
    "url": "https://sandbox.cashfree.com/pg/orders/order02/payments" // production URL’s are different
  },
  "refunds": {
    "url": "https://sandbox.cashfree.com/pg/orders/order02/refunds"
  },
  "settlements": {
    "url": "https://sandbox.cashfree.com/pg/orders/order02/settlements"
  },
  "terminal_data": null
}

Order Pay
This API returns the UPI intent url that contains the parameters required for the WhatsApp APIs. Reference doc
Request
curl --request POST \
     --url https://sandbox.cashfree.com/pg/orders/sessions \
     --header 'accept: application/json' \
     --header 'content-type: application/json' \
     --header 'x-api-version: 2022-09-01' \
     --data '
    {
     "payment_method": {
          "upi": {
               "channel": "link",
               "upi_id": "rajnandan1@okhdfcbak",
               "upi_expiry_minutes": 10
          }
     },
     "payment_session_id": "session_364o8HjN0-gc6n_n4EBEPOXriJUJvCeVIdy9u8ihOhwvpNg9F1wMorWmkVxUR90kTe473bpbotNxyZ6Fze8M0w42_BpTxoEWsbBR21y7i0nh" // this is from the create order API response
    }

Response
{
  "action": "custom",
  "cf_payment_id": 885899755, // is the transaction ID, is also present in UPI url
  "channel": "link",
  "data": {
    "url": null,
    "payload": {
      "bhim": "https://payments-test.cashfree.com/pgbillpayuiapi/simulator/885899755?txnId=885899755&amount=200.50&pa=cashfree@testbank&pn=Cashfree&tr=885899755&am=200.50&cu=INR&mode=00&purpose=00&mc=5732&tn=Cashfree%20Simulator%20Payment",
      "default": "https://payments-test.cashfree.com/pgbillpayuiapi/simulator/885899755?txnId=885899755&amount=200.50&pa=cashfree@testbank&pn=Cashfree&tr=885899755&am=200.50&cu=INR&mode=00&purpose=00&mc=5732&tn=Cashfree%20Simulator%20Payment",
      "gpay": "https://payments-test.cashfree.com/pgbillpayuiapi/simulator/885899755?txnId=885899755&amount=200.50&pa=cashfree@testbank&pn=Cashfree&tr=885899755&am=200.50&cu=INR&mode=00&purpose=00&mc=5732&tn=Cashfree%20Simulator%20Payment",
      "paytm": "https://payments-test.cashfree.com/pgbillpayuiapi/simulator/885899755?txnId=885899755&amount=200.50&pa=cashfree@testbank&pn=Cashfree&tr=885899755&am=200.50&cu=INR&mode=00&purpose=00&mc=5732&tn=Cashfree%20Simulator%20Payment",
      "phonepe": "https://payments-test.cashfree.com/pgbillpayuiapi/simulator/885899755?txnId=885899755&amount=200.50&pa=cashfree@testbank&pn=Cashfree&tr=885899755&am=200.50&cu=INR&mode=00&purpose=00&mc=5732&tn=Cashfree%20Simulator%20Payment",
      "web": "https://sandbox.cashfree.com/pg/view/upi/qcrgfb.session_364o8HjN0-gc6n_n4EBEPOXriJUJvCeVIdy9u8ihOhwvpNg9F1wMorWmkVxUR90kTe473bpbotNxyZ6Fze8M0w42_BpTxoEWsbBR21y7i0nh.c252cd27-c877-4a51-8352-837d04a2f4c2"
    },
    "content_type": null,
    "method": null
  },
  "payment_amount": 200.5,
  "payment_method": "upi"
}

Parsing the response
Store the cf_payment_id as a unique identifier of the payment at Cashfree end. As Cashfree supports multiple payments for a given order_id (or cf_order_id), storing the cf_payment_id is important for deduping multiple/duplicate payments (if they occur due to a bug or otherwise).
Extract the key-value pairs in data.payload.default
Verify “am” to be the same as the amount that was set.
Use the value in “tr” as the reference_id while setting up the Parameters object to send the payment message using WhatsApp API.
The value of “pa” is the merchant vpa that will be used for this transaction. The payment configuration name corresponding to the vpa returned should be used as payment_configuration while setting up the Parameters object to send the payment message using WhatsApp API.
In case the merchant vpa obtained from above does not match with any of the vpas set in the WhatsApp payment configuration, payment should be discontinued. Please reach out to Cashfree to confirm the updated vpa and update the WhatsApp payment configuration accordingly.
Also check whether the “mode” and “purpose” values are the same as those set in the payment configuration. In case of mismatch, log the mismatch to follow up with Cashfree about the right/updated values. Do not block the payment due to this mismatch.
"default": "upi://pay?pa=cfsukoonaa@yesbank&pn=Sukoon&tr=877376394&am=10.00&cu=INR&mode=00&purpose=00&mc=5399&tn=877376394"

Webhook
Once the user completes the payment on WhatsApp, Cashfree will send a webhook about payment completion. Please note that while WhatsApp also shares a payment completion signal, please rely on the signal from Cashfree for the final payment status to avoid reconciliation issues.
Based on the payment_status in the webhook, update the order status for the user using the WhatsApp API.
Reference Guide
Successful transaction webhook
{
  "data": {
    "order": {
      "order_id": "1633615918",
      "order_amount": 1.00,
      "order_currency": "INR",
      "order_tags": null
    },
    "payment": {
      "cf_payment_id": 1107253,
      "payment_status": "SUCCESS",
      "payment_amount": 1.00,
      "payment_currency": "INR",
      "payment_message": "Transaction pending",
      "payment_time": "2021-10-07T19:42:40+05:30",
      "bank_reference": "1903772466",
      "auth_id": null,
      "payment_method": {
        "upi": {
          "channel":null,
          "upi_id":"miglaniyogesh7@okhdfcbank" }
                },
       "payment_group":"upi"
    "customer_details": {
      "customer_name": "Yogesh",
      "customer_id": "12121212",
      "customer_email": "yogesh.miglani@gmail.com",
      "customer_phone": "9666699999"
    }
  },
  "event_time": "2021-10-07T19:42:44+05:30",
  "type": "PAYMENT_SUCCESS_WEBHOOK"
}

Failed transaction webhook
{
  "data": {
    "order": {
      "order_id": "order_01",
      "order_amount": 2,
      "order_currency": "INR",
      "order_tags": null
    },
    "payment": {
      "cf_payment_id": 975677709,
      "payment_status": "FAILED",
      "payment_amount": 2,
      "payment_currency": "INR",
      "payment_message": "ZA::U19::Transaction fail",
      "payment_time": "2022-05-25T14:28:22+05:30",
      "bank_reference": "214568722700",
      "auth_id": null,
      "payment_method": {
        "upi": {
          "channel": null,
          "upi_id": "9611199227@paytm"
        }
      },
      "payment_group": "upi"
    },
    "customer_details": {
      "customer_name": null,
      "customer_id": "7112AAA812234",
      "customer_email": "miglaniyogesh7@gmail.com",
      "customer_phone": "9611199227"
    },
    "error_details": {
      "error_code": "TRANSACTION_DECLINED",
      "error_description": "issuer bank or payment service provider declined the transaction",
      "error_reason": "auth_declined",
      "error_source": "customer"
    }
  },
  "event_time": "2022-05-25T14:28:38+05:30",
  "type": "PAYMENT_FAILED_WEBHOOK"
}

Status Check
Status api can be used as an alternative in case the webhook isn’t received within a certain timeframe. Based on the payment_status in the response, update the order status for the user using the WhatsApp API.
Reference Doc
Request
curl --request GET \
     --url https://sandbox.cashfree.com/pg/orders/order02/payments/885899755 \
     --header 'accept: application/json' \
     --header 'x-api-version: 2022-09-01' \
     --header 'x-client-id: 26268833355ef02b8ff299390c886262' \
     --header 'x-client-secret: 1708cc38a3c1c3c2512d79b3530dc5cc65ad2fde'

Response
{
  "auth_id": null,
  "authorization": null,
  "bank_reference": null,
  "cf_payment_id": 885704957,
  "entity": "payment",
  "error_details": null,
  "is_captured": true,
  "order_amount": 10.15,
  "order_id": "12345",
  "payment_amount": 10.15,
  "payment_completion_time": "2022-10-27T08:43:05+05:30",
  "payment_currency": "INR",
  "payment_group": "upi",
  "payment_message": "Transaction Successful",
  "payment_method": {
    "upi": {
      "channel": "link"
    }
  },
  "payment_status": "SUCCESS",
  "payment_time": "2022-10-27T08:42:07+05:30"
}

OR

{
  "auth_id": null,
  "authorization": null,
  "bank_reference": null,
  "cf_payment_id": 885899755,
  "entity": "payment",
  "error_details": null,
  "is_captured": false,
  "order_amount": 200.5,
  "order_id": "order02",
  "payment_amount": 200.5,
  "payment_completion_time": "2022-12-26T14:24:56+05:30",
  "payment_currency": "INR",
  "payment_gateway_details": null,
  "payment_group": "upi",
  "payment_message": "User dropped and did not complete the two factor authentication",
  "payment_method": {
    "upi": {
      "channel": "link",
      "upi_id": "987836150"
    }
  },
  "payment_status": "USER_DROPPED",
  "payment_time": "2022-12-26T14:14:56+05:30"
}

Refund
Refund api can be used to trigger refunds to the user.
Reference Doc
Request
curl --request POST \
     --url https://sandbox.cashfree.com/pg/orders/12345/refunds \
     --header 'accept: application/json' \
     --header 'content-type: application/json' \
     --header 'x-api-version: 2022-01-01' \
     --header 'x-client-id: xxxxxx' \
     --header 'x-client-secret: xxxxxx' \
     --data '
    {
     "refund_amount": 5,
     "refund_id": "refund12345"
    }

Response
{
  "cf_payment_id": 885704957,
  "cf_refund_id": "refund_49234",
  "created_at": "2022-10-27T14:35:22+05:30",
  "entity": "refund",
  "metadata": null,
  "order_id": "12345",
  "processed_at": null,
  "refund_amount": 5,
  "refund_arn": null,
  "refund_charge": 0,
  "refund_currency": "INR",
  "refund_id": "refund12345",
  "refund_mode": "STANDARD",
  "refund_note": null,
  "refund_splits": [],
  "refund_status": "PENDING",
  "refund_type": "MERCHANT_INITIATED",
  "status_description": "In Progress"
}

Handling Special cases
Order Expiry
Cashfree allows setting the expiry time for an order in the Create Order API. Use that to set preferred expiry time.
Post order expiry, if no webhook was received, do a status check to ensure that the order expired and then cancel the order at WhatsApp to update the user.
Handling failed payments
The Payment message sent to the user via Whatsapp allows for multiple retries upon failure (ie the Pay button is available until successful payment). However Cashfree requires the reference id (“tr” field in the url received in Order Pay response) to be unique for each payment.
So when a failed payment response is received from Cashfree, update the status of order at WhatsApp to cancelled. Post that a new payment message can be sent to the user to retry the payment.
In case, there is a delay in cancellation and the user ends up making a successful payment, Cashfree will not send a webhook to the merchant but does an auto-refund, without any additional action required by the merchant. In the case of a customer query in such a scenario (where they claim the transaction was successful but the payment cannot be found at Cashfree), suggest to the user that refund will be processed in a few days.
Canceling Order for successful transaction
There may arise a scenario where Cashfree shared a successful payment signal but the order cannot be fulfilled by the merchant. In such scenario, process refund for the payment via one of the following mechanisms:
Use Refund API.
Use Cashfree dashboard for merchants.