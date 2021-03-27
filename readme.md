
### Who should read this document?

This guide is written for merchants who have signed up through PayOne Payment Gateway system to use it as their e-Payment processor and EZ-Connect Interface as their integration point for handling electronic transactions (payment, refund, confirm.. etc.) and from different payment methods (credit card, debit card...etc.), by using the HTTPS Post as programming interface to perform the transactions. In particular, it describes the format for sending transactions and the corresponding received responses.

### Merchant’s admin/developer must make sure to:
* Store the Secret Key in a secure place as a secure database or file.
* Change the Secret Key periodically according to the Merchant Organization’s Security
policies.
* Not store the Secret Key within the source code of an ASP, JSP or any web page standing the
chance of being accessed or viewed via web.

### Communication Model

**Redirection communication model:** in this model Merchant site issues Http redirection command to customer (card holder) browser; customer gets redirected to PG system payment site where the customer is requested to provide some input to complete the cycle. After conducting the payment, the PG system redirects the customer back to the merchant site based on a predefined merchant’s URL.

### Request Flow
* Merchant prepares request message which includes request’s fields based on the message type or action (i.e. Pay-Web, Refund ... etc.)
* Merchant generates Secure Hash using request parameters and Secret Key value
* Merchant sends request and Secure Hash to PG.
* PG System upon receiving the request will retrieve Secret Key value stored for this merchant at PG side.
* PG System regenerates Secure Hash using received request parameters and merchant Secret Key stored at PG System.
* PG compares generated Secure Hash with received Secure Hash, if values mismatch, request will be rejected. Else PG will continue processing request.