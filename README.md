# PayPal - Merchant Gateway Integration

PayPal is a payment processing gatway integration used to process transactions via API.

## Getting Started

Authorization credentials provided by PayPal is required in order to test the API.

### Prerequisites

The API can be achieved successfully via basic http request with POST method. $this->apiUsername, $this->apiPassword, and $this->apiSignature can be exchanged with credentials provided by PayPal.

```
https://www.paypal.com/cgi-bin/webscr?cmd=_express-checkout?apiUsername=johnDoe&apiPassword=doe123&apiSignature=893493841&...;
```

## Running the tests

Testing was achieved through test cards provided by PayPal. Sale, Authorize, Capture, Void, and Refund were the methods of the transactions. 

### Break down into end to end tests

The test card was tested once a successful response was received. Random debit card credentials were tested.

## Authors

* **Brian Beal** - *Initial work* - [bealdev](https://github.com/bealdev)
