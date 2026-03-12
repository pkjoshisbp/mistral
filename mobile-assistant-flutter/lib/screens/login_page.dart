import 'package:flutter/material.dart';

import '../models/user_session.dart';
import '../services/api_client.dart';

class LoginPage extends StatefulWidget {
  const LoginPage({
    super.key,
    required this.initialBaseUrl,
    required this.onLogin,
  });

  final String initialBaseUrl;
  final Future<void> Function(UserSession session, String baseUrl) onLogin;

  @override
  State<LoginPage> createState() => _LoginPageState();
}

class _LoginPageState extends State<LoginPage> {
  final _formKey = GlobalKey<FormState>();
  final _emailCtrl = TextEditingController();
  final _passwordCtrl = TextEditingController();
  final _deviceCtrl = TextEditingController(text: 'flutter-client');
  late final TextEditingController _baseCtrl;

  bool _loading = false;
  String _error = '';

  @override
  void initState() {
    super.initState();
    _baseCtrl = TextEditingController(text: widget.initialBaseUrl);
  }

  @override
  void dispose() {
    _emailCtrl.dispose();
    _passwordCtrl.dispose();
    _deviceCtrl.dispose();
    _baseCtrl.dispose();
    super.dispose();
  }

  Future<void> _submit() async {
    if (!_formKey.currentState!.validate()) {
      return;
    }

    setState(() {
      _loading = true;
      _error = '';
    });

    try {
      final client = ApiClient(baseUrl: _baseCtrl.text.trim());
      final session = await client.login(
        email: _emailCtrl.text.trim(),
        password: _passwordCtrl.text,
        deviceName: _deviceCtrl.text.trim().isEmpty ? 'flutter-client' : _deviceCtrl.text.trim(),
      );
      await widget.onLogin(session, _baseCtrl.text.trim());
    } catch (e) {
      setState(() {
        _error = e.toString().replaceFirst('Exception: ', '');
      });
    } finally {
      if (mounted) {
        setState(() {
          _loading = false;
        });
      }
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      body: Center(
        child: SingleChildScrollView(
          padding: const EdgeInsets.all(20),
          child: ConstrainedBox(
            constraints: const BoxConstraints(maxWidth: 420),
            child: Card(
              elevation: 2,
              child: Padding(
                padding: const EdgeInsets.all(20),
                child: Form(
                  key: _formKey,
                  child: Column(
                    mainAxisSize: MainAxisSize.min,
                    crossAxisAlignment: CrossAxisAlignment.stretch,
                    children: [
                      Text('AI Personal Assistant', style: Theme.of(context).textTheme.headlineSmall),
                      const SizedBox(height: 6),
                      const Text('Login to continue'),
                      const SizedBox(height: 20),
                      TextFormField(
                        controller: _baseCtrl,
                        decoration: const InputDecoration(labelText: 'API Base URL', border: OutlineInputBorder()),
                        validator: (v) => (v == null || v.trim().isEmpty) ? 'Required' : null,
                      ),
                      const SizedBox(height: 12),
                      TextFormField(
                        controller: _emailCtrl,
                        decoration: const InputDecoration(labelText: 'Email', border: OutlineInputBorder()),
                        validator: (v) => (v == null || !v.contains('@')) ? 'Enter valid email' : null,
                      ),
                      const SizedBox(height: 12),
                      TextFormField(
                        controller: _passwordCtrl,
                        obscureText: true,
                        decoration: const InputDecoration(labelText: 'Password', border: OutlineInputBorder()),
                        validator: (v) => (v == null || v.isEmpty) ? 'Password required' : null,
                      ),
                      const SizedBox(height: 12),
                      TextFormField(
                        controller: _deviceCtrl,
                        decoration: const InputDecoration(labelText: 'Device Name', border: OutlineInputBorder()),
                      ),
                      if (_error.isNotEmpty) ...[
                        const SizedBox(height: 12),
                        Text(_error, style: const TextStyle(color: Colors.red)),
                      ],
                      const SizedBox(height: 16),
                      FilledButton.icon(
                        onPressed: _loading ? null : _submit,
                        icon: _loading
                            ? const SizedBox(width: 18, height: 18, child: CircularProgressIndicator(strokeWidth: 2))
                            : const Icon(Icons.login),
                        label: Text(_loading ? 'Signing in...' : 'Sign In'),
                      ),
                    ],
                  ),
                ),
              ),
            ),
          ),
        ),
      ),
    );
  }
}
