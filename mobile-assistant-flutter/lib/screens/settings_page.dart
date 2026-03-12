import 'package:flutter/material.dart';

import '../services/api_client.dart';

class SettingsPage extends StatefulWidget {
  const SettingsPage({super.key, required this.apiClient});

  final ApiClient apiClient;

  @override
  State<SettingsPage> createState() => _SettingsPageState();
}

class _SettingsPageState extends State<SettingsPage> {
  final _customVocabCtrl = TextEditingController();
  final _correctionMapCtrl = TextEditingController();

  String _language = 'en';
  String _ttsProvider = 'xtts';
  String _trainingMode = 'sentences';

  bool _loading = false;
  String _info = '';
  List<String> _trainingSamples = [];

  @override
  void initState() {
    super.initState();
    _load();
  }

  @override
  void dispose() {
    _customVocabCtrl.dispose();
    _correctionMapCtrl.dispose();
    super.dispose();
  }

  Future<void> _load() async {
    setState(() {
      _loading = true;
      _info = '';
    });

    try {
      final settings = await widget.apiClient.getSettings();
      final vocab = (settings['custom_vocabulary'] as List<dynamic>? ?? const []).map((e) => e.toString()).toList();
      final correctionMap = (settings['correction_map'] as Map<String, dynamic>? ?? {});
      final correctionLines = correctionMap.entries.map((e) => '${e.key} => ${e.value}').toList();

      final samplesPayload = await widget.apiClient.getTrainingSamples(_trainingMode);
      final samples = (samplesPayload['samples'] as List<dynamic>? ?? const []).map((e) => e.toString()).toList();

      setState(() {
        _language = (settings['preferred_language'] ?? 'en').toString();
        _ttsProvider = (settings['tts_provider'] ?? 'xtts').toString();
        _customVocabCtrl.text = vocab.join('\n');
        _correctionMapCtrl.text = correctionLines.join('\n');
        _trainingSamples = samples;
      });
    } catch (e) {
      setState(() {
        _info = e.toString().replaceFirst('Exception: ', '');
      });
    } finally {
      if (mounted) {
        setState(() => _loading = false);
      }
    }
  }

  Future<void> _saveSettings() async {
    setState(() {
      _loading = true;
      _info = '';
    });

    try {
      final vocab = _customVocabCtrl.text
          .split(RegExp(r'[\r\n,]+'))
          .map((e) => e.trim())
          .where((e) => e.isNotEmpty)
          .toSet()
          .toList();

      final correction = <String, String>{};
      for (final line in _correctionMapCtrl.text.split(RegExp(r'[\r\n]+'))) {
        final parts = line.split('=>');
        if (parts.length < 2) continue;
        final source = parts.first.trim();
        final target = parts.sublist(1).join('=>').trim();
        if (source.isNotEmpty && target.isNotEmpty) {
          correction[source] = target;
        }
      }

      await widget.apiClient.updateSettings(
        preferredLanguage: _language,
        ttsProvider: _ttsProvider,
        customVocabulary: vocab,
        correctionMap: correction,
      );

      setState(() => _info = 'Settings saved successfully.');
    } catch (e) {
      setState(() => _info = e.toString().replaceFirst('Exception: ', ''));
    } finally {
      if (mounted) {
        setState(() => _loading = false);
      }
    }
  }

  Future<void> _reloadSamples() async {
    setState(() {
      _loading = true;
      _info = '';
    });

    try {
      final payload = await widget.apiClient.getTrainingSamples(_trainingMode);
      final samples = (payload['samples'] as List<dynamic>? ?? const []).map((e) => e.toString()).toList();
      setState(() => _trainingSamples = samples);
    } catch (e) {
      setState(() => _info = e.toString().replaceFirst('Exception: ', ''));
    } finally {
      if (mounted) {
        setState(() => _loading = false);
      }
    }
  }

  Future<void> _saveTrainingSample(String sample) async {
    final transcriptCtrl = TextEditingController(text: sample);
    final correctedCtrl = TextEditingController(text: sample);

    final result = await showDialog<bool>(
      context: context,
      builder: (_) => AlertDialog(
        title: const Text('Save Training Correction'),
        content: SizedBox(
          width: 480,
          child: Column(
            mainAxisSize: MainAxisSize.min,
            children: [
              Text('Sample: $sample'),
              const SizedBox(height: 10),
              TextField(
                controller: transcriptCtrl,
                minLines: 2,
                maxLines: 4,
                decoration: const InputDecoration(labelText: 'Transcript', border: OutlineInputBorder()),
              ),
              const SizedBox(height: 10),
              TextField(
                controller: correctedCtrl,
                minLines: 2,
                maxLines: 4,
                decoration: const InputDecoration(labelText: 'Corrected', border: OutlineInputBorder()),
              ),
            ],
          ),
        ),
        actions: [
          TextButton(onPressed: () => Navigator.pop(context, false), child: const Text('Cancel')),
          FilledButton(onPressed: () => Navigator.pop(context, true), child: const Text('Save')),
        ],
      ),
    );

    if (result != true) return;

    setState(() {
      _loading = true;
      _info = '';
    });

    try {
      final payload = await widget.apiClient.saveTrainingCorrection(
        sampleText: sample,
        transcript: transcriptCtrl.text.trim(),
        corrected: correctedCtrl.text.trim(),
        mode: _trainingMode,
      );

      setState(() => _info = (payload['message'] ?? 'Saved').toString());
    } catch (e) {
      setState(() => _info = e.toString().replaceFirst('Exception: ', ''));
    } finally {
      if (mounted) {
        setState(() => _loading = false);
      }
    }
  }

  @override
  Widget build(BuildContext context) {
    return ListView(
      padding: const EdgeInsets.all(16),
      children: [
        Card(
          child: Padding(
            padding: const EdgeInsets.all(14),
            child: Column(
              children: [
                Row(
                  children: [
                    Expanded(
                      child: DropdownButtonFormField<String>(
                        value: _language,
                        decoration: const InputDecoration(labelText: 'Preferred Language'),
                        items: const [
                          DropdownMenuItem(value: 'en', child: Text('English')),
                          DropdownMenuItem(value: 'hi', child: Text('Hindi')),
                          DropdownMenuItem(value: 'te', child: Text('Telugu')),
                          DropdownMenuItem(value: 'ta', child: Text('Tamil')),
                          DropdownMenuItem(value: 'bn', child: Text('Bengali')),
                        ],
                        onChanged: (v) => setState(() => _language = v ?? 'en'),
                      ),
                    ),
                    const SizedBox(width: 10),
                    Expanded(
                      child: DropdownButtonFormField<String>(
                        value: _ttsProvider,
                        decoration: const InputDecoration(labelText: 'TTS Provider'),
                        items: const [
                          DropdownMenuItem(value: 'xtts', child: Text('XTTS')), 
                          DropdownMenuItem(value: 'indic', child: Text('Indic')), 
                          DropdownMenuItem(value: 'auto', child: Text('Auto')),
                        ],
                        onChanged: (v) => setState(() => _ttsProvider = v ?? 'xtts'),
                      ),
                    ),
                  ],
                ),
                const SizedBox(height: 10),
                TextField(
                  controller: _customVocabCtrl,
                  minLines: 3,
                  maxLines: 6,
                  decoration: const InputDecoration(
                    labelText: 'Custom Vocabulary (one per line)',
                    border: OutlineInputBorder(),
                  ),
                ),
                const SizedBox(height: 10),
                TextField(
                  controller: _correctionMapCtrl,
                  minLines: 3,
                  maxLines: 6,
                  decoration: const InputDecoration(
                    labelText: 'Correction Map (wrong => correct)',
                    border: OutlineInputBorder(),
                  ),
                ),
                const SizedBox(height: 10),
                Align(
                  alignment: Alignment.centerRight,
                  child: FilledButton.icon(
                    onPressed: _loading ? null : _saveSettings,
                    icon: _loading
                        ? const SizedBox(width: 14, height: 14, child: CircularProgressIndicator(strokeWidth: 2))
                        : const Icon(Icons.save),
                    label: const Text('Save Settings'),
                  ),
                ),
              ],
            ),
          ),
        ),
        const SizedBox(height: 12),
        Card(
          child: Padding(
            padding: const EdgeInsets.all(14),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Row(
                  children: [
                    Expanded(
                      child: DropdownButtonFormField<String>(
                        value: _trainingMode,
                        decoration: const InputDecoration(labelText: 'Training Mode'),
                        items: const [
                          DropdownMenuItem(value: 'sentences', child: Text('Sentences')),
                          DropdownMenuItem(value: 'phrases', child: Text('Phrases')),
                          DropdownMenuItem(value: 'paragraphs', child: Text('Paragraphs')),
                        ],
                        onChanged: (v) {
                          setState(() => _trainingMode = v ?? 'sentences');
                          _reloadSamples();
                        },
                      ),
                    ),
                    IconButton(onPressed: _loading ? null : _reloadSamples, icon: const Icon(Icons.refresh)),
                  ],
                ),
                const SizedBox(height: 8),
                Text('Training Samples', style: Theme.of(context).textTheme.titleSmall),
                const SizedBox(height: 8),
                ..._trainingSamples.map(
                  (sample) => ListTile(
                    contentPadding: EdgeInsets.zero,
                    title: Text(sample),
                    trailing: TextButton(
                      onPressed: _loading ? null : () => _saveTrainingSample(sample),
                      child: const Text('Save Correction'),
                    ),
                  ),
                ),
              ],
            ),
          ),
        ),
        if (_info.isNotEmpty)
          Padding(
            padding: const EdgeInsets.only(top: 8),
            child: Text(
              _info,
              style: TextStyle(color: _info.toLowerCase().contains('success') ? Colors.green : Colors.blueGrey),
            ),
          ),
      ],
    );
  }
}
