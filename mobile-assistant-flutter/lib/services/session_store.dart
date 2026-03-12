import 'package:shared_preferences/shared_preferences.dart';

import '../models/user_session.dart';

class SessionStore {
  static const _kToken = 'pa_token';
  static const _kName = 'pa_name';
  static const _kEmail = 'pa_email';
  static const _kRole = 'pa_role';
  static const _kApiBase = 'pa_api_base';

  Future<void> saveSession(UserSession session) async {
    final prefs = await SharedPreferences.getInstance();
    await prefs.setString(_kToken, session.token);
    await prefs.setString(_kName, session.name);
    await prefs.setString(_kEmail, session.email);
    await prefs.setString(_kRole, session.role);
  }

  Future<UserSession?> loadSession() async {
    final prefs = await SharedPreferences.getInstance();
    final token = prefs.getString(_kToken);
    if (token == null || token.isEmpty) {
      return null;
    }

    return UserSession.fromStorage({
      'token': token,
      'name': prefs.getString(_kName) ?? '',
      'email': prefs.getString(_kEmail) ?? '',
      'role': prefs.getString(_kRole) ?? 'customer',
    });
  }

  Future<void> clearSession() async {
    final prefs = await SharedPreferences.getInstance();
    await prefs.remove(_kToken);
    await prefs.remove(_kName);
    await prefs.remove(_kEmail);
    await prefs.remove(_kRole);
  }

  Future<String> loadApiBase() async {
    final prefs = await SharedPreferences.getInstance();
    return prefs.getString(_kApiBase) ?? 'https://ai-chat.support/api/mobile-assistant';
  }

  Future<void> saveApiBase(String url) async {
    final prefs = await SharedPreferences.getInstance();
    await prefs.setString(_kApiBase, url.trim());
  }
}
