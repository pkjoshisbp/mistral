import 'package:flutter/material.dart';
import 'package:intl/intl.dart';

import '../models/assistant_item.dart';

class ItemEditorResult {
  final String type;
  final String title;
  final String content;
  final String status;
  final String? dueAt;
  final List<String> tags;

  ItemEditorResult({
    required this.type,
    required this.title,
    required this.content,
    required this.status,
    required this.dueAt,
    required this.tags,
  });
}

class ItemEditorDialog extends StatefulWidget {
  const ItemEditorDialog({super.key, this.item});

  final AssistantItem? item;

  @override
  State<ItemEditorDialog> createState() => _ItemEditorDialogState();
}

class _ItemEditorDialogState extends State<ItemEditorDialog> {
  late TextEditingController _titleCtrl;
  late TextEditingController _contentCtrl;
  late TextEditingController _tagsCtrl;

  late String _type;
  late String _status;
  DateTime? _due;

  @override
  void initState() {
    super.initState();
    _type = widget.item?.type ?? 'note';
    _status = widget.item?.status ?? 'pending';
    _titleCtrl = TextEditingController(text: widget.item?.title ?? '');
    _contentCtrl = TextEditingController(text: widget.item?.content ?? '');
    _tagsCtrl = TextEditingController(text: (widget.item?.tags ?? const []).join(', '));
    if (widget.item?.dueAt != null && widget.item!.dueAt!.isNotEmpty) {
      _due = DateTime.tryParse(widget.item!.dueAt!);
    }
  }

  @override
  void dispose() {
    _titleCtrl.dispose();
    _contentCtrl.dispose();
    _tagsCtrl.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    return AlertDialog(
      title: Text(widget.item == null ? 'New Item' : 'Edit Item #${widget.item!.id}'),
      content: SingleChildScrollView(
        child: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            DropdownButtonFormField<String>(
              value: _type,
              decoration: const InputDecoration(labelText: 'Type'),
              items: const ['note', 'task', 'reminder', 'dictation', 'email_draft', 'email']
                  .map((e) => DropdownMenuItem(value: e, child: Text(e)))
                  .toList(),
              onChanged: (v) => setState(() => _type = v ?? 'note'),
            ),
            const SizedBox(height: 10),
            TextField(
              controller: _titleCtrl,
              decoration: const InputDecoration(labelText: 'Title', border: OutlineInputBorder()),
            ),
            const SizedBox(height: 10),
            TextField(
              controller: _contentCtrl,
              minLines: 3,
              maxLines: 6,
              decoration: const InputDecoration(labelText: 'Content', border: OutlineInputBorder()),
            ),
            const SizedBox(height: 10),
            DropdownButtonFormField<String>(
              value: _status,
              decoration: const InputDecoration(labelText: 'Status'),
              items: const ['pending', 'saved', 'completed', 'draft', 'pending_confirmation', 'sent', 'send_failed']
                  .map((e) => DropdownMenuItem(value: e, child: Text(e)))
                  .toList(),
              onChanged: (v) => setState(() => _status = v ?? 'pending'),
            ),
            const SizedBox(height: 10),
            TextField(
              controller: _tagsCtrl,
              decoration: const InputDecoration(labelText: 'Tags (comma separated)', border: OutlineInputBorder()),
            ),
            const SizedBox(height: 10),
            Row(
              children: [
                Expanded(
                  child: Text(
                    _due == null ? 'No due date' : DateFormat('y-MM-dd HH:mm').format(_due!),
                    style: const TextStyle(fontSize: 12),
                  ),
                ),
                TextButton.icon(
                  onPressed: () async {
                    final date = await showDatePicker(
                      context: context,
                      firstDate: DateTime.now().subtract(const Duration(days: 3650)),
                      lastDate: DateTime.now().add(const Duration(days: 3650)),
                      initialDate: _due ?? DateTime.now(),
                    );
                    if (date == null || !context.mounted) return;
                    final time = await showTimePicker(
                      context: context,
                      initialTime: TimeOfDay.fromDateTime(_due ?? DateTime.now()),
                    );
                    if (time == null) return;
                    setState(() {
                      _due = DateTime(date.year, date.month, date.day, time.hour, time.minute);
                    });
                  },
                  icon: const Icon(Icons.schedule),
                  label: const Text('Pick Due Date'),
                ),
              ],
            ),
          ],
        ),
      ),
      actions: [
        TextButton(onPressed: () => Navigator.pop(context), child: const Text('Cancel')),
        FilledButton(
          onPressed: () {
            final tags = _tagsCtrl.text
                .split(',')
                .map((e) => e.trim())
                .where((e) => e.isNotEmpty)
                .toSet()
                .toList();

            Navigator.pop(
              context,
              ItemEditorResult(
                type: _type,
                title: _titleCtrl.text.trim(),
                content: _contentCtrl.text.trim(),
                status: _status,
                dueAt: _due?.toIso8601String(),
                tags: tags,
              ),
            );
          },
          child: const Text('Save'),
        ),
      ],
    );
  }
}
