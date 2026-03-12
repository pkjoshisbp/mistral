import 'dart:convert';
import 'dart:io';

import 'package:http/http.dart' as http;

import '../models/assistant_item.dart';
import '../models/user_session.dart';

class ApiClient {
  String baseUrl;
  String? token;

  ApiClient({required this.baseUrl, this.token});

  Map<String, String> _headers({bool json = true}) {
    final headers = <String, String>{};
    if (json) {
      headers['Content-Type'] = 'application/json';
    }
    if (token != null && token!.isNotEmpty) {
      headers['Authorization'] = 'Bearer $token';
    }
    return headers;
  }

  Uri _uri(String path, [Map<String, dynamic>? query]) {
    final uri = Uri.parse('$baseUrl$path');
    if (query == null || query.isEmpty) {
      return uri;
    }

    return uri.replace(queryParameters: query.map((k, v) => MapEntry(k, v.toString())));
  }

  Future<UserSession> login({
    required String email,
    required String password,
    required String deviceName,
  }) async {
    final response = await http.post(
      _uri('/login'),
      headers: _headers(),
      body: jsonEncode({
        'email': email,
        'password': password,
        'device_name': deviceName,
      }),
    );

    final data = jsonDecode(response.body) as Map<String, dynamic>;
    if (response.statusCode >= 400) {
      throw Exception(data['message']?.toString() ?? 'Login failed');
    }

    final user = data['user'] as Map<String, dynamic>? ?? {};
    final session = UserSession(
      token: data['token'].toString(),
      name: (user['name'] ?? '').toString(),
      email: (user['email'] ?? '').toString(),
      role: (user['role'] ?? 'customer').toString(),
    );
    token = session.token;
    return session;
  }

  Future<void> logout() async {
    await http.post(_uri('/logout'), headers: _headers());
    token = null;
  }

  Future<Map<String, dynamic>> me() async {
    final response = await http.get(_uri('/me'), headers: _headers());
    final data = jsonDecode(response.body) as Map<String, dynamic>;
    if (response.statusCode >= 400) {
      throw Exception(data['message']?.toString() ?? 'Failed to load profile');
    }
    return data;
  }

  Future<Map<String, dynamic>> getSettings() async {
    final response = await http.get(_uri('/settings'), headers: _headers());
    final data = jsonDecode(response.body) as Map<String, dynamic>;
    if (response.statusCode >= 400) {
      throw Exception(data['message']?.toString() ?? 'Failed to load settings');
    }
    return data;
  }

  Future<void> updateSettings({
    required String preferredLanguage,
    required String ttsProvider,
    required List<String> customVocabulary,
    required Map<String, String> correctionMap,
  }) async {
    final response = await http.put(
      _uri('/settings'),
      headers: _headers(),
      body: jsonEncode({
        'preferred_language': preferredLanguage,
        'tts_provider': ttsProvider,
        'custom_vocabulary': customVocabulary,
        'correction_map': correctionMap,
      }),
    );

    final data = jsonDecode(response.body) as Map<String, dynamic>;
    if (response.statusCode >= 400) {
      throw Exception(data['message']?.toString() ?? 'Failed to save settings');
    }
  }

  Future<Map<String, dynamic>> getTrainingSamples(String mode) async {
    final response = await http.get(
      _uri('/training/samples', {'mode': mode}),
      headers: _headers(),
    );
    final data = jsonDecode(response.body) as Map<String, dynamic>;
    if (response.statusCode >= 400) {
      throw Exception('Failed to load training samples');
    }
    return data;
  }

  Future<Map<String, dynamic>> saveTrainingCorrection({
    required String sampleText,
    required String transcript,
    required String corrected,
    required String mode,
  }) async {
    final response = await http.post(
      _uri('/training/save-correction'),
      headers: _headers(),
      body: jsonEncode({
        'sample_text': sampleText,
        'transcript': transcript,
        'corrected': corrected,
        'mode': mode,
      }),
    );

    final data = jsonDecode(response.body) as Map<String, dynamic>;
    if (response.statusCode >= 400) {
      throw Exception(data['message']?.toString() ?? 'Failed to save correction');
    }
    return data;
  }

  Future<Map<String, dynamic>> transcribeAudio(File audioFile, {String language = 'en'}) async {
    final request = http.MultipartRequest('POST', _uri('/voice/transcribe'));
    request.headers.addAll(_headers(json: false));
    request.fields['language'] = language;
    request.fields['provider'] = 'auto';
    request.files.add(await http.MultipartFile.fromPath('audio', audioFile.path));

    final streamed = await request.send();
    final response = await http.Response.fromStream(streamed);
    final data = jsonDecode(response.body) as Map<String, dynamic>;
    if (response.statusCode >= 400) {
      throw Exception(data['message']?.toString() ?? 'Transcription failed');
    }
    return data;
  }

  Future<Map<String, dynamic>> processCommand({
    required String inputText,
    String transcript = '',
    String editedTranscript = '',
    bool withTts = false,
  }) async {
    final response = await http.post(
      _uri('/commands/process'),
      headers: _headers(),
      body: jsonEncode({
        'input_text': inputText,
        'transcript': transcript,
        'edited_transcript': editedTranscript,
        'with_tts': withTts,
      }),
    );

    final data = jsonDecode(response.body) as Map<String, dynamic>;
    if (response.statusCode >= 400) {
      throw Exception(data['message']?.toString() ?? 'Command failed');
    }
    return data;
  }

  Future<List<AssistantItem>> listItems({String query = '', String type = ''}) async {
    final params = <String, dynamic>{'per_page': 100};
    if (query.trim().isNotEmpty) {
      params['q'] = query.trim();
    }
    if (type.trim().isNotEmpty) {
      params['type'] = type.trim();
    }

    final response = await http.get(_uri('/items', params), headers: _headers());
    final data = jsonDecode(response.body) as Map<String, dynamic>;
    if (response.statusCode >= 400) {
      throw Exception(data['message']?.toString() ?? 'Failed to fetch items');
    }

    final rows = (data['data'] as List<dynamic>? ?? []);
    return rows.map((e) => AssistantItem.fromJson(e as Map<String, dynamic>)).toList();
  }

  Future<AssistantItem> createItem({
    required String type,
    required String title,
    required String content,
    required String status,
    String? dueAt,
    List<String> tags = const [],
  }) async {
    final response = await http.post(
      _uri('/items'),
      headers: _headers(),
      body: jsonEncode({
        'type': type,
        'title': title,
        'content': content,
        'status': status,
        'due_at': dueAt,
        'tags': tags,
      }),
    );

    final data = jsonDecode(response.body) as Map<String, dynamic>;
    if (response.statusCode >= 400) {
      throw Exception(data['message']?.toString() ?? 'Failed to create item');
    }

    return AssistantItem.fromJson(data['item'] as Map<String, dynamic>);
  }

  Future<AssistantItem> updateItem({
    required int id,
    required String type,
    required String title,
    required String content,
    required String status,
    String? dueAt,
    List<String> tags = const [],
  }) async {
    final response = await http.put(
      _uri('/items/$id'),
      headers: _headers(),
      body: jsonEncode({
        'type': type,
        'title': title,
        'content': content,
        'status': status,
        'due_at': dueAt,
        'tags': tags,
      }),
    );

    final data = jsonDecode(response.body) as Map<String, dynamic>;
    if (response.statusCode >= 400) {
      throw Exception(data['message']?.toString() ?? 'Failed to update item');
    }

    return AssistantItem.fromJson(data['item'] as Map<String, dynamic>);
  }

  Future<void> deleteItem(int id) async {
    final response = await http.delete(_uri('/items/$id'), headers: _headers());
    final data = jsonDecode(response.body) as Map<String, dynamic>;
    if (response.statusCode >= 400) {
      throw Exception(data['message']?.toString() ?? 'Failed to delete item');
    }
  }
}
