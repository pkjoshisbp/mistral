import 'package:flutter/material.dart';

import 'models/user_session.dart';
import 'screens/home_shell.dart';
import 'screens/login_page.dart';
import 'services/api_client.dart';
import 'services/session_store.dart';

void main() {
  runApp(const AssistantApp());
}

class AssistantApp extends StatefulWidget {
  const AssistantApp({super.key});

  @override
  State<AssistantApp> createState() => _AssistantAppState();
}

class _AssistantAppState extends State<AssistantApp> {
  final SessionStore _store = SessionStore();
  ApiClient? _client;
  UserSession? _session;
  String _baseUrl = 'https://ai-chat.support/api/mobile-assistant';
  bool _loading = true;

  @override
  void initState() {
    super.initState();
    _bootstrap();
  }

  Future<void> _bootstrap() async {
    final base = await _store.loadApiBase();
    final session = await _store.loadSession();

    setState(() {
      _baseUrl = base;
      _session = session;
      _client = ApiClient(baseUrl: base, token: session?.token);
      _loading = false;
    });
  }

  Future<void> _onLogin(UserSession session, String baseUrl) async {
    await _store.saveApiBase(baseUrl);
    await _store.saveSession(session);
    setState(() {
      _baseUrl = baseUrl;
      _session = session;
      _client = ApiClient(baseUrl: baseUrl, token: session.token);
    });
  }

  Future<void> _onLogout() async {
    try {
      await _client?.logout();
    } catch (_) {}
    await _store.clearSession();
    setState(() {
      _session = null;
      _client = ApiClient(baseUrl: _baseUrl);
    });
  }

  @override
  Widget build(BuildContext context) {
    return MaterialApp(
      title: 'Personal Assistant',
      debugShowCheckedModeBanner: false,
      theme: ThemeData(
        colorScheme: ColorScheme.fromSeed(seedColor: const Color(0xFF2A6CF0)),
        useMaterial3: true,
        scaffoldBackgroundColor: const Color(0xFFF4F7FC),
      ),
      home: _loading
          ? const Scaffold(body: Center(child: CircularProgressIndicator()))
          : (_session == null || _client == null)
              ? LoginPage(
                  initialBaseUrl: _baseUrl,
                  onLogin: _onLogin,
                )
              : HomeShell(
                  apiClient: _client!,
                  session: _session!,
                  onLogout: _onLogout,
                ),
    );
  }
}
