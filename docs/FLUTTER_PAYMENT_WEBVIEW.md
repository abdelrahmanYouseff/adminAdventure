# Flutter WebView Payment Integration

This document describes how to integrate Noon payment in a Flutter app using a WebView, with URL interception so the app never shows a 500 error and can close the WebView on success/fail.

## Backend requirements

- When creating a payment session **from the app**, send `from_app: true` (or `source: "app"`) so the return URL includes `from_app=1`. The callback will then always return 200 with minimal HTML.
- Success URL: `https://your-domain.com/payment/success?order_id=ORD-xxx&from_app=1`
- Fail URL: `https://your-domain.com/payment/fail?order_id=ORD-xxx&from_app=1`

## 1. Create payment session from Flutter

```dart
// When calling your API to create payment session, include from_app: true
final response = await http.post(
  Uri.parse('https://your-api.com/api/payment/create'),
  headers: {'Content-Type': 'application/json', 'Authorization': 'Bearer $token'},
  body: jsonEncode({
    'user_id': userId,
    'amount': amount,
    'currency': 'SAR',
    'order_id': orderId,  // your order_number (created before this step)
    'customer_email': email,
    'customer_name': name,
    'customer_phone': phone,
    'description': 'Order $orderId',
    'from_app': true,   // so return URL gets from_app=1
  }),
);
final data = jsonDecode(response.body);
final checkoutUrl = data['data']['checkout_url'];
// Open checkoutUrl in WebView
```

## 2. WebView with URL interception (flutter_inappwebview)

```dart
import 'package:flutter_inappwebview/flutter_inappwebview.dart';

class PaymentWebView extends StatefulWidget {
  final String checkoutUrl;
  final String successHost;  // e.g. 'your-domain.com'
  final void Function(String orderId) onPaymentSuccess;
  final void Function() onPaymentFail;

  const PaymentWebView({
    required this.checkoutUrl,
    required this.successHost,
    required this.onPaymentSuccess,
    required this.onPaymentFail,
  });

  @override
  State<PaymentWebView> createState() => _PaymentWebViewState();
}

class _PaymentWebViewState extends State<PaymentWebView> {
  @override
  Widget build(BuildContext context) {
    return InAppWebView(
      initialUrlRequest: URLRequest(url: WebUri(widget.checkoutUrl)),
      initialSettings: InAppWebViewSettings(
        javaScriptEnabled: true,
        allowFileAccess: false,
        useHybridComposition: true,
      ),
      onLoadStart: (controller, url) {
        if (url == null) return;
        _handleUrl(url.toString());
      },
      shouldOverrideUrlLoading: (controller, navigationAction) async {
        final url = navigationAction.request.url?.toString() ?? '';
        if (_handleUrl(url)) {
          return NavigationActionPolicy.CANCEL;
        }
        return NavigationActionPolicy.ALLOW;
      },
    );
  }

  bool _handleUrl(String url) {
    if (!url.contains(widget.successHost)) return false;

    if (url.contains('/payment/success')) {
      final orderId = _parseQueryParam(url, 'order_id');
      if (orderId != null && orderId.isNotEmpty) {
        widget.onPaymentSuccess(orderId);
        if (mounted) Navigator.of(context).pop();
        return true;
      }
    }

    if (url.contains('/payment/fail') || url.contains('/payment/cancel')) {
      widget.onPaymentFail();
      if (mounted) Navigator.of(context).pop();
      return true;
    }

    return false;
  }

  String? _parseQueryParam(String urlString, String key) {
    try {
      final uri = Uri.parse(urlString);
      return uri.queryParameters[key];
    } catch (_) {
      return null;
    }
  }
}
```

## 3. Using the WebView

```dart
void openPaymentWebView(String checkoutUrl) {
  Navigator.of(context).push(
    MaterialPageRoute(
      builder: (context) => Scaffold(
        appBar: AppBar(title: const Text('الدفع')),
        body: PaymentWebView(
          checkoutUrl: checkoutUrl,
          successHost: 'your-domain.com',
          onPaymentSuccess: (orderId) {
            // Optionally poll GET /api/payment/status?session_id=$orderId
            // then call handlePaymentSuccess (POST/GET your confirm endpoint)
            _clearCart();
            _showSuccess();
            Navigator.of(context).popUntil((route) => route.isFirst);
          },
          onPaymentFail: () {
            _showFail();
          },
        ),
      ),
    ),
  );
}
```

## 4. Optional: Polling and confirm

While the user is on the payment page (or after redirect), you can poll until paid:

```dart
Future<bool> waitForPaymentStatus(String orderId) async {
  for (var i = 0; i < 60; i++) {
    await Future.delayed(const Duration(seconds: 5));
    final res = await http.get(
      Uri.parse('https://your-api.com/api/payment/status?session_id=$orderId'),
    );
    final data = jsonDecode(res.body);
    final status = data['status'] ?? data['payment_status'] ?? data['data']?['status'];
    if (status == 'completed' || status == 'success' || status == 'paid') {
      return true;
    }
  }
  return false;
}
```

After success (from URL or polling), call your confirm endpoint if needed:

```dart
await http.get(
  Uri.parse('https://your-api.com/api/payment/success?order_id=$orderId'),
);
```

## 5. Prevent blank page

- Do not close the WebView before the success URL is loaded or intercepted. Use `shouldOverrideUrlLoading` or `onLoadStart` to detect the success URL, then close after calling `onPaymentSuccess`.
- The backend returns 200 with minimal HTML for `from_app=1`; if you intercept the URL and close immediately, the user may not see the page. You can either let the page load (shows "تم الدفع بنجاح") and then auto-close after a short delay, or close as soon as you intercept (faster UX).

## 6. WebView (webview_flutter) alternative

If using `webview_flutter` with `NavigationDelegate`:

```dart
WebView(
  initialUrl: checkoutUrl,
  javascriptMode: JavascriptMode.unrestricted,
  navigationDelegate: (NavigationRequest request) {
    if (request.url.contains('/payment/success')) {
      final orderId = Uri.parse(request.url).queryParameters['order_id'];
      if (orderId != null) {
        onPaymentSuccess(orderId);
        Navigator.of(context).pop();
        return NavigationDecision.prevent;
      }
    }
    if (request.url.contains('/payment/fail') || request.url.contains('/payment/cancel')) {
      onPaymentFail();
      Navigator.of(context).pop();
      return NavigationDecision.prevent;
    }
    return NavigationDecision.navigate;
  },
);
```

## Summary

1. Pass `from_app: true` when creating the payment session so the return URL includes `from_app=1`.
2. Intercept navigation to URLs containing `/payment/success` and `/payment/fail` (or `/payment/cancel`).
3. Parse `order_id` from the success URL and run your success flow (clear cart, show message, pop WebView).
4. Optionally poll `GET /api/payment/status?session_id=...` and call the success API for confirmation.
