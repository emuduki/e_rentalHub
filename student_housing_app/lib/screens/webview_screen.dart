import 'package:flutter/material.dart';
import 'dart:io' show Platform;
import 'dart:math';
import 'package:webview_flutter/webview_flutter.dart';

class WebViewScreen extends StatefulWidget {
  const WebViewScreen({super.key});

  @override
  State<WebViewScreen> createState() => _WebViewScreenState();
}

class _WebViewScreenState extends State<WebViewScreen> {
  late WebViewController _webViewController;
  bool _isLoading = true;
  bool _hasError = false;
  String? _errorMessage;
  bool _canGoBack = false;
  bool _canGoForward = false;

  @override
  void initState() {
    super.initState();
    if (Platform.isAndroid || Platform.isIOS) {
      _initializeMobileWebView();
    }
  }

  String _getServerUrl() {
    if (Platform.isAndroid) {
      // For physical devices, use your machine's IP
      // For Android emulator, use 10.0.2.2
      return 'http://192.168.0.108/e_rentalHub/';
    } else if (Platform.isIOS) {
      // iOS simulator uses localhost
      // For physical device, replace with your machine's IP (e.g., http://192.168.1.100)
      return 'http://localhost/e_rentalHub/';
    }
    return 'http://localhost/e_rentalHub/';
  }

  void _initializeMobileWebView() {
    _webViewController = WebViewController()
      ..setJavaScriptMode(JavaScriptMode.unrestricted)
      ..setNavigationDelegate(
        NavigationDelegate(
          onPageStarted: (String url) {
            setState(() {
              _isLoading = true;
              _hasError = false;
            });
            _updateNavigationButtons();
          },
          onPageFinished: (String url) {
            setState(() {
              _isLoading = false;
            });
            _updateNavigationButtons();
          },
          onWebResourceError: (WebResourceError error) {
            setState(() {
              _hasError = true;
              _errorMessage = error.description;
              _isLoading = false;
            });
          },
        ),
      );

    // Disable caching completely by clearing cache
    _webViewController.clearCache();

    // Load with aggressive cache-busting: use random suffix each time
    _webViewController.loadRequest(Uri.parse(_getServerUrlWithBuster()));
  }

  String _getServerUrlWithBuster() {
    final base = _getServerUrl();
    final sep = base.contains('?') ? '&' : '?';
    // Use both timestamp and random number for maximum cache-busting
    final timestamp = DateTime.now().millisecondsSinceEpoch;
    final random = Random().nextInt(1000000);
    return '$base${sep}v=${timestamp}_$random';
  }

  Future<void> _updateNavigationButtons() async {
    final canGoBack = await _webViewController.canGoBack();
    final canGoForward = await _webViewController.canGoForward();
    setState(() {
      _canGoBack = canGoBack;
      _canGoForward = canGoForward;
    });
  }

  Future<void> _goBack() async {
    if (await _webViewController.canGoBack()) {
      await _webViewController.goBack();
    }
  }

  Future<void> _goForward() async {
    if (await _webViewController.canGoForward()) {
      await _webViewController.goForward();
    }
  }

  Future<void> _refresh() async {
    await _webViewController.reload();
  }

  Future<void> _retryConnection() async {
    setState(() {
      _hasError = false;
      _isLoading = true;
    });
    // retry with fresh cache-busting param
    await _webViewController.clearCache();
    await _webViewController.loadRequest(Uri.parse(_getServerUrlWithBuster()));
  }

  @override
  Widget build(BuildContext context) {
    // Mobile: Show WebView
    if (Platform.isAndroid || Platform.isIOS) {
      return Scaffold(
        appBar: AppBar(
          title: const Text('e_rentalHub'),
          elevation: 0,
          backgroundColor: Colors.deepPurple.shade600,
          leading: _canGoBack
              ? IconButton(
                  icon: const Icon(Icons.arrow_back),
                  onPressed: _goBack,
                )
              : null,
          actions: [
            IconButton(
              icon: const Icon(Icons.arrow_forward),
              onPressed: _canGoForward ? _goForward : null,
            ),
            IconButton(icon: const Icon(Icons.refresh), onPressed: _refresh),
          ],
        ),
        body: _hasError
            ? Center(
                child: Padding(
                  padding: const EdgeInsets.all(20.0),
                  child: Column(
                    mainAxisAlignment: MainAxisAlignment.center,
                    children: [
                      const Icon(
                        Icons.error_outline,
                        size: 80,
                        color: Colors.red,
                      ),
                      const SizedBox(height: 20),
                      const Text(
                        'Connection Error',
                        style: TextStyle(
                          fontSize: 20,
                          fontWeight: FontWeight.bold,
                        ),
                      ),
                      const SizedBox(height: 10),
                      Text(
                        _errorMessage ?? 'Unable to connect',
                        textAlign: TextAlign.center,
                        style: const TextStyle(fontSize: 14),
                      ),
                      const SizedBox(height: 30),
                      ElevatedButton.icon(
                        onPressed: _retryConnection,
                        icon: const Icon(Icons.refresh),
                        label: const Text('Retry'),
                        style: ElevatedButton.styleFrom(
                          backgroundColor: Colors.deepPurple.shade600,
                          padding: const EdgeInsets.symmetric(
                            horizontal: 30,
                            vertical: 12,
                          ),
                        ),
                      ),
                    ],
                  ),
                ),
              )
            : Stack(
                children: [
                  WebViewWidget(controller: _webViewController),
                  if (_isLoading)
                    Container(
                      color: Colors.black12,
                      child: const Center(child: CircularProgressIndicator()),
                    ),
                ],
              ),
      );
    }

    // Desktop: Show message with URL
    return Scaffold(
      appBar: AppBar(
        title: const Text('e_rentalHub'),
        elevation: 0,
        backgroundColor: Colors.deepPurple.shade600,
      ),
      body: Center(
        child: Padding(
          padding: const EdgeInsets.all(40.0),
          child: Column(
            mainAxisAlignment: MainAxisAlignment.center,
            children: [
              const Icon(Icons.web, size: 100, color: Colors.deepPurple),
              const SizedBox(height: 40),
              const Text(
                'e_rentalHub',
                style: TextStyle(fontSize: 32, fontWeight: FontWeight.bold),
              ),
              const SizedBox(height: 20),
              Container(
                padding: const EdgeInsets.all(20),
                decoration: BoxDecoration(
                  color: Colors.deepPurple.shade50,
                  borderRadius: BorderRadius.circular(12),
                  border: Border.all(color: Colors.deepPurple.shade200),
                ),
                child: Column(
                  children: [
                    const Text(
                      'Open your browser to:',
                      style: TextStyle(fontSize: 16, color: Colors.grey),
                    ),
                    const SizedBox(height: 15),
                    SelectableText(
                      'http://localhost/e_rentalHub/',
                      style: TextStyle(
                        fontSize: 16,
                        fontWeight: FontWeight.bold,
                        color: Colors.deepPurple.shade600,
                        fontFamily: 'monospace',
                      ),
                    ),
                  ],
                ),
              ),
              const SizedBox(height: 40),
              ElevatedButton(
                onPressed: () {
                  ScaffoldMessenger.of(context).showSnackBar(
                    const SnackBar(
                      content: Text('📋 URL copied to clipboard'),
                      duration: Duration(seconds: 2),
                    ),
                  );
                },
                style: ElevatedButton.styleFrom(
                  backgroundColor: Colors.deepPurple.shade600,
                  padding: const EdgeInsets.symmetric(
                    horizontal: 30,
                    vertical: 15,
                  ),
                ),
                child: const Text(
                  'Open in Browser',
                  style: TextStyle(fontSize: 16),
                ),
              ),
            ],
          ),
        ),
      ),
    );
  }
}
