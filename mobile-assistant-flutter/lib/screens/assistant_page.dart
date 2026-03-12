import 'dart:io';

import 'package:file_picker/file_picker.dart';
import 'package:flutter/material.dart';

import '../services/api_client.dart';

class AssistantPage extends StatefulWidget {
  const AssistantPage({super.key, required this.apiClient});

  final ApiClient apiClient;

  @override
  State<AssistantPage> createState() => _AssistantPageState();
}

class _AssistantPageState extends State<AssistantPage> {
  final _inputCtrl = TextEditingController();
  final _transcriptCtrl = TextEditingController();
  final _editedCtrl = TextEditingController();

  bool _busy = false;
  String _reply = '';
  String _status = '';

  @override
  void dispose() {
    _inputCtrl.dispose();
    _transcriptCtrl.dispose();
    _editedCtrl.dispose();
    super.dispose();
  }

  Future<void> _pickAndTranscribe() async {
    setState(() {
      _busy = true;
      _status = '';
    });

    try {
      final result = await FilePicker.platform.pickFiles(type: FileType.audio);
      if (result == null || result.files.single.path == null) {
        return;
      }

      final file = File(result.files.single.path!);
      final data = await widget.apiClient.transcribeAudio(file);
      _transcriptCtrl.text = (data['transcript'] ?? '').toString();
      _editedCtrl.text = (data['edited_transcript'] ?? '').toString();
      _status = 'Transcription complete.';
    } catch (e) {
      _status = e.toString().replaceFirst('Exception: ', '');
    } finally {
      if (mounted) {
        setState(() {
          _busy = false;
        });
      }
    }
  }

  Future<void> _runCommand() async {
    final inputText = _inputCtrl.text.trim();
    final editedText = _editedCtrl.text.trim();

    if (inputText.isEmpty && editedText.isEmpty) {
      setState(() => _status = 'Type a command or transcribe audio first.');
      return;
    }

    setState(() {
      _busy = true;
      _status = '';
    });

    try {
      final result = await widget.apiClient.processCommand(
        inputText: inputText,
        transcript: _transcriptCtrl.text,
        editedTranscript: editedText,
      );

      setState(() {
        _reply = (result['reply'] ?? '').toString();
        _status = (result['status'] ?? '').toString();
      });
    } catch (e) {
      setState(() {
        _status = e.toString().replaceFirst('Exception: ', '');
      });
    } finally {
      if (mounted) {
        setState(() {
          _busy = false;
        });
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
              crossAxisAlignment: CrossAxisAlignment.stretch,
              children: [
                Text('Assistant Console', style: Theme.of(context).textTheme.titleMedium),
                const SizedBox(height: 10),
                TextField(
                  controller: _inputCtrl,
                  minLines: 2,
                  maxLines: 4,
                  decoration: const InputDecoration(
                    labelText: 'Manual Command Input',
                    hintText: 'Type command, e.g. Add reminder for tomorrow 10 AM',
                    border: OutlineInputBorder(),
                  ),
                ),
                const SizedBox(height: 10),
                Row(
                  children: [
                    Expanded(
                      child: OutlinedButton.icon(
                        onPressed: _busy ? null : _pickAndTranscribe,
                        icon: const Icon(Icons.audio_file),
                        label: const Text('Upload & Transcribe Audio'),
                      ),
                    ),
                    const SizedBox(width: 8),
                    Expanded(
                      child: FilledButton.icon(
                        onPressed: _busy ? null : _runCommand,
                        icon: _busy
                            ? const SizedBox(width: 16, height: 16, child: CircularProgressIndicator(strokeWidth: 2))
                            : const Icon(Icons.play_arrow),
                        label: Text(_busy ? 'Processing...' : 'Run Command'),
                      ),
                    ),
                  ],
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
              crossAxisAlignment: CrossAxisAlignment.stretch,
              children: [
                TextField(
                  controller: _transcriptCtrl,
                  minLines: 2,
                  maxLines: 4,
                  decoration: const InputDecoration(labelText: 'Transcript', border: OutlineInputBorder()),
                ),
                const SizedBox(height: 10),
                TextField(
                  controller: _editedCtrl,
                  minLines: 2,
                  maxLines: 4,
                  decoration: const InputDecoration(labelText: 'Editable Transcript', border: OutlineInputBorder()),
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
                Text('Assistant Reply', style: Theme.of(context).textTheme.titleSmall),
                const SizedBox(height: 8),
                Text(_reply.isEmpty ? 'No response yet.' : _reply),
                if (_status.isNotEmpty) ...[
                  const SizedBox(height: 8),
                  Text('Status: $_status', style: const TextStyle(color: Colors.blueGrey)),
                ],
              ],
            ),
          ),
        ),
      ],
    );
  }
}
