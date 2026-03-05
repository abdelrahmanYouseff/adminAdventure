# Professional Payment Flow — Flutter Implementation

## الحزم المطلوبة (`pubspec.yaml`)

```yaml
dependencies:
  flutter_inappwebview: ^6.1.5
  http: ^1.2.1
```

---

## 1. نموذج بيانات الدفع (`payment_result.dart`)

```dart
enum PaymentStatus { pending, completed, failed, notFound }

class PaymentResult {
  final PaymentStatus status;
  final String? orderId;
  final String? orderNumber;
  final double? amount;
  final String currency;
  final String? paymentMethod;

  const PaymentResult({
    required this.status,
    this.orderId,
    this.orderNumber,
    this.amount,
    this.currency = 'SAR',
    this.paymentMethod,
  });

  factory PaymentResult.fromJson(Map<String, dynamic> json) {
    final data = json['data'] as Map<String, dynamic>? ?? {};
    final statusStr = (json['status'] ?? data['status'] ?? '').toString();

    PaymentStatus s;
    switch (statusStr) {
      case 'completed':
        s = PaymentStatus.completed;
        break;
      case 'failed':
        s = PaymentStatus.failed;
        break;
      case 'pending':
        s = PaymentStatus.pending;
        break;
      default:
        s = PaymentStatus.notFound;
    }

    return PaymentResult(
      status: s,
      orderId: data['order_id']?.toString(),
      orderNumber: data['order_number']?.toString(),
      amount: double.tryParse(data['amount']?.toString() ?? ''),
      currency: data['currency']?.toString() ?? 'SAR',
      paymentMethod: data['payment_method']?.toString(),
    );
  }
}
```

---

## 2. خدمة الدفع (`payment_service.dart`)

```dart
import 'dart:convert';
import 'package:http/http.dart' as http;
import 'payment_result.dart';

class PaymentService {
  static const String baseUrl = 'https://www.admin.adventureksa.com';

  /// الخطوة 1: إنشاء جلسة الدفع — يعيد checkout_url و order_id
  static Future<Map<String, dynamic>> createPaymentSession({
    required String userId,
    required double amount,
    required String currency,
    required String orderId,       // رقم فريد تولده قبل الاستدعاء (مثال: UUID أو timestamp)
    required String customerName,
    required String customerEmail,
    String? customerPhone,
    String? description,
  }) async {
    final response = await http.post(
      Uri.parse('$baseUrl/api/payment/create'),
      headers: {
        'Content-Type': 'application/json',
        'Accept': 'application/json',
      },
      body: jsonEncode({
        'user_id': userId,
        'amount': amount,
        'currency': currency,
        'order_id': orderId,
        'customer_name': customerName,
        'customer_email': customerEmail,
        if (customerPhone != null) 'customer_phone': customerPhone,
        'description': description ?? 'طلب من تطبيق عالم المغامرات',
        'from_app': true,        // مهم: يجعل returnUrl يحتوي على from_app=1
      }),
    );

    if (response.statusCode == 201) {
      final body = jsonDecode(response.body) as Map<String, dynamic>;
      if (body['success'] == true) {
        return {
          'checkout_url': body['data']['checkout_url'] as String,
          'order_id': body['data']['order_id'] as String,
        };
      }
      throw Exception(body['message'] ?? 'فشل في إنشاء جلسة الدفع');
    }
    throw Exception('Server error: ${response.statusCode}');
  }

  /// الخطوة 3: Polling — الاستعلام عن حالة الدفع
  /// يعيد PaymentStatus.completed أو .failed أو .pending أو .notFound
  static Future<PaymentResult> checkPaymentStatus(String orderId) async {
    final response = await http.get(
      Uri.parse('$baseUrl/api/payment/status?session_id=$orderId'),
      headers: {'Accept': 'application/json'},
    );

    if (response.statusCode == 200 || response.statusCode == 404) {
      final body = jsonDecode(response.body) as Map<String, dynamic>;
      return PaymentResult.fromJson(body);
    }
    throw Exception('Status check error: ${response.statusCode}');
  }
}
```

---

## 3. صفحة الدفع الكاملة (`payment_webview_page.dart`)

تحتوي على:
- فتح WebView مع URL interception
- إغلاق WebView عند الـ redirect
- بدء Polling تلقائياً
- إظهار شاشة النتيجة

```dart
import 'dart:async';
import 'package:flutter/material.dart';
import 'package:flutter_inappwebview/flutter_inappwebview.dart';
import 'payment_service.dart';
import 'payment_result.dart';

class PaymentWebViewPage extends StatefulWidget {
  final String checkoutUrl;
  final String orderId;
  final VoidCallback? onSuccess;
  final VoidCallback? onFailure;

  const PaymentWebViewPage({
    super.key,
    required this.checkoutUrl,
    required this.orderId,
    this.onSuccess,
    this.onFailure,
  });

  @override
  State<PaymentWebViewPage> createState() => _PaymentWebViewPageState();
}

class _PaymentWebViewPageState extends State<PaymentWebViewPage> {
  bool _isLoading = true;
  bool _pollingStarted = false;

  // ─── URL Interceptor ────────────────────────────────────────────────────────

  Future<NavigationActionPolicy> _shouldOverrideUrlLoading(
    InAppWebViewController controller,
    NavigationAction action,
  ) async {
    final url = action.request.url?.toString() ?? '';

    if (url.contains('/payment/success')) {
      if (!_pollingStarted) {
        _pollingStarted = true;
        if (mounted) Navigator.of(context).pop();   // أغلق الـ WebView
        _startPolling();
      }
      return NavigationActionPolicy.CANCEL;
    }

    if (url.contains('/payment/fail') || url.contains('/payment/cancel')) {
      if (mounted) {
        Navigator.of(context).pop();
        widget.onFailure?.call();
        _showResultDialog(success: false);
      }
      return NavigationActionPolicy.CANCEL;
    }

    return NavigationActionPolicy.ALLOW;
  }

  // ─── Polling ────────────────────────────────────────────────────────────────

  Future<void> _startPolling() async {
    const maxAttempts = 10;
    const interval = Duration(seconds: 3);

    for (int i = 0; i < maxAttempts; i++) {
      await Future.delayed(interval);

      try {
        final result = await PaymentService.checkPaymentStatus(widget.orderId);

        if (result.status == PaymentStatus.completed) {
          widget.onSuccess?.call();
          if (mounted) _showResultDialog(success: true, result: result);
          return;
        }

        if (result.status == PaymentStatus.failed) {
          widget.onFailure?.call();
          if (mounted) _showResultDialog(success: false);
          return;
        }

        // pending / notFound → keep polling
      } catch (_) {
        // network error → keep polling
      }
    }

    // انتهت المحاولات بدون نتيجة — أظهر شاشة "جارٍ التحقق"
    if (mounted) _showPendingScreen();
  }

  // ─── Result Dialogs ──────────────────────────────────────────────────────────

  void _showResultDialog({required bool success, PaymentResult? result}) {
    showDialog(
      context: context,
      barrierDismissible: false,
      builder: (_) => AlertDialog(
        shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(16)),
        title: Row(
          children: [
            Icon(
              success ? Icons.check_circle : Icons.error,
              color: success ? Colors.green : Colors.red,
              size: 28,
            ),
            const SizedBox(width: 8),
            Text(success ? 'تم الدفع بنجاح' : 'فشل الدفع'),
          ],
        ),
        content: success && result != null
            ? Column(
                mainAxisSize: MainAxisSize.min,
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text('رقم الطلب: ${result.orderNumber ?? result.orderId}'),
                  if (result.amount != null)
                    Text(
                      'المبلغ: ${result.amount!.toStringAsFixed(2)} ${result.currency}',
                    ),
                ],
              )
            : const Text('حدث خطأ أثناء الدفع. يرجى المحاولة مرة أخرى.'),
        actions: [
          TextButton(
            onPressed: () {
              Navigator.of(context).pop();        // أغلق الـ Dialog
              Navigator.of(context).pop(success); // ارجع للشاشة السابقة
            },
            child: const Text('موافق'),
          ),
        ],
      ),
    );
  }

  void _showPendingScreen() {
    showDialog(
      context: context,
      barrierDismissible: false,
      builder: (_) => AlertDialog(
        shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(16)),
        title: const Row(
          children: [
            Icon(Icons.hourglass_top, color: Colors.orange),
            SizedBox(width: 8),
            Text('جارٍ التحقق من الدفع'),
          ],
        ),
        content: const Text(
          'تمت عملية الدفع ولكن لم نتمكن من التحقق بعد.\n'
          'ستصلك إشعار بالنتيجة قريباً.',
        ),
        actions: [
          TextButton(
            onPressed: () {
              Navigator.of(context).pop();
              Navigator.of(context).pop(null);
            },
            child: const Text('حسناً'),
          ),
        ],
      ),
    );
  }

  // ─── Build ───────────────────────────────────────────────────────────────────

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(
        title: const Text('إتمام الدفع'),
        centerTitle: true,
        leading: IconButton(
          icon: const Icon(Icons.close),
          onPressed: () => Navigator.of(context).pop(false),
        ),
      ),
      body: Stack(
        children: [
          InAppWebView(
            initialUrlRequest: URLRequest(
              url: WebUri(widget.checkoutUrl),
            ),
            initialSettings: InAppWebViewSettings(
              javaScriptEnabled: true,
              domStorageEnabled: true,
              useShouldOverrideUrlLoading: true,
            ),
            shouldOverrideUrlLoading: _shouldOverrideUrlLoading,
            onLoadStop: (_, __) => setState(() => _isLoading = false),
            onLoadError: (_, __, ___, ____) => setState(() => _isLoading = false),
          ),
          if (_isLoading)
            const Center(child: CircularProgressIndicator()),
        ],
      ),
    );
  }
}
```

---

## 4. كيفية الاستخدام من أي شاشة

```dart
Future<void> _onCheckoutPressed() async {
  // ─ 1. توليد order_id فريد ─
  final orderId = 'APP-${DateTime.now().millisecondsSinceEpoch}';

  // ─ 2. إنشاء جلسة الدفع ─
  late final String checkoutUrl;
  try {
    final session = await PaymentService.createPaymentSession(
      userId: currentUser.id,
      amount: cartTotal,
      currency: 'SAR',
      orderId: orderId,
      customerName: currentUser.name,
      customerEmail: currentUser.email,
      customerPhone: currentUser.phone,
    );
    checkoutUrl = session['checkout_url']!;
  } catch (e) {
    ScaffoldMessenger.of(context).showSnackBar(
      SnackBar(content: Text('فشل في بدء الدفع: $e')),
    );
    return;
  }

  // ─ 3. فتح صفحة الدفع ─
  final result = await Navigator.of(context).push<bool>(
    MaterialPageRoute(
      builder: (_) => PaymentWebViewPage(
        checkoutUrl: checkoutUrl,
        orderId: orderId,
        onSuccess: () => _handleSuccess(orderId),
        onFailure: () => _handleFailure(),
      ),
    ),
  );

  if (result == true) {
    // الدفع تأكد — انتقل لشاشة التأكيد
  }
}

void _handleSuccess(String orderId) {
  // مثلاً: افرغ السلة، انتقل لصفحة "شكراً"
}

void _handleFailure() {
  // مثلاً: أظهر رسالة فشل
}
```

---

## 5. ملاحظات مهمة

### NOON_WEBHOOK_SECRET
بعد تسجيل webhook في لوحة نون، أضف القيمة في `.env`:
```
NOON_WEBHOOK_SECRET=قيمة_السر_من_لوحة_نون
```
ثم نفّذ على السيرفر: `php artisan config:clear`

### Webhook URL المطلوب تسجيله في لوحة نون
```
https://www.admin.adventureksa.com/api/payment/webhook
```

### ترتيب الأحداث بعد الدفع
1. نون ترسل Webhook → السيرفر يُنشئ Invoice + Order (status=paid)
2. نون تعيد توجيه المتصفح → Flutter يكتشف الـ URL ويغلق الـ WebView
3. Flutter يبدأ Polling كل 3 ثواني → يحصل على `completed` من `/api/payment/status`
4. Flutter يُظهر شاشة النجاح

### وقت التنفيذ
- Webhook يصل في أقل من ثانية عادةً
- أول polling request (بعد 3 ثواني) في الغالب يجد `completed`
