import 'package:flutter/material.dart';
import 'package:intl/intl.dart';

import '../models/assistant_item.dart';
import '../services/api_client.dart';
import '../widgets/item_editor_dialog.dart';

class ItemsPage extends StatefulWidget {
  const ItemsPage({super.key, required this.apiClient});

  final ApiClient apiClient;

  @override
  State<ItemsPage> createState() => _ItemsPageState();
}

class _ItemsPageState extends State<ItemsPage> {
  final _searchCtrl = TextEditingController();
  final _typeCtrl = TextEditingController();
  bool _loading = false;
  String _error = '';
  List<AssistantItem> _items = [];

  @override
  void initState() {
    super.initState();
    _loadItems();
  }

  @override
  void dispose() {
    _searchCtrl.dispose();
    _typeCtrl.dispose();
    super.dispose();
  }

  Future<void> _loadItems() async {
    setState(() {
      _loading = true;
      _error = '';
    });

    try {
      final rows = await widget.apiClient.listItems(
        query: _searchCtrl.text.trim(),
        type: _typeCtrl.text.trim(),
      );
      setState(() => _items = rows);
    } catch (e) {
      setState(() => _error = e.toString().replaceFirst('Exception: ', ''));
    } finally {
      if (mounted) {
        setState(() => _loading = false);
      }
    }
  }

  Future<void> _createItem() async {
    final result = await showDialog<ItemEditorResult>(
      context: context,
      builder: (_) => const ItemEditorDialog(),
    );
    if (result == null) return;

    try {
      await widget.apiClient.createItem(
        type: result.type,
        title: result.title,
        content: result.content,
        status: result.status,
        dueAt: result.dueAt,
        tags: result.tags,
      );
      await _loadItems();
    } catch (e) {
      setState(() => _error = e.toString().replaceFirst('Exception: ', ''));
    }
  }

  Future<void> _editItem(AssistantItem item) async {
    final result = await showDialog<ItemEditorResult>(
      context: context,
      builder: (_) => ItemEditorDialog(item: item),
    );
    if (result == null) return;

    try {
      await widget.apiClient.updateItem(
        id: item.id,
        type: result.type,
        title: result.title,
        content: result.content,
        status: result.status,
        dueAt: result.dueAt,
        tags: result.tags,
      );
      await _loadItems();
    } catch (e) {
      setState(() => _error = e.toString().replaceFirst('Exception: ', ''));
    }
  }

  Future<void> _deleteItem(AssistantItem item) async {
    final yes = await showDialog<bool>(
      context: context,
      builder: (_) => AlertDialog(
        title: const Text('Delete Item'),
        content: Text('Delete item #${item.id}?'),
        actions: [
          TextButton(onPressed: () => Navigator.pop(context, false), child: const Text('Cancel')),
          FilledButton(onPressed: () => Navigator.pop(context, true), child: const Text('Delete')),
        ],
      ),
    );

    if (yes != true) return;

    try {
      await widget.apiClient.deleteItem(item.id);
      await _loadItems();
    } catch (e) {
      setState(() => _error = e.toString().replaceFirst('Exception: ', ''));
    }
  }

  @override
  Widget build(BuildContext context) {
    return Column(
      children: [
        Padding(
          padding: const EdgeInsets.all(12),
          child: Row(
            children: [
              Expanded(
                child: TextField(
                  controller: _searchCtrl,
                  decoration: const InputDecoration(
                    hintText: 'Search title/content',
                    border: OutlineInputBorder(),
                    isDense: true,
                  ),
                ),
              ),
              const SizedBox(width: 8),
              SizedBox(
                width: 120,
                child: TextField(
                  controller: _typeCtrl,
                  decoration: const InputDecoration(
                    hintText: 'Type',
                    border: OutlineInputBorder(),
                    isDense: true,
                  ),
                ),
              ),
              const SizedBox(width: 8),
              IconButton(onPressed: _loading ? null : _loadItems, icon: const Icon(Icons.search)),
              IconButton(onPressed: _loading ? null : _createItem, icon: const Icon(Icons.add_circle)),
            ],
          ),
        ),
        if (_error.isNotEmpty)
          Padding(
            padding: const EdgeInsets.symmetric(horizontal: 12),
            child: Align(
              alignment: Alignment.centerLeft,
              child: Text(_error, style: const TextStyle(color: Colors.red)),
            ),
          ),
        Expanded(
          child: _loading
              ? const Center(child: CircularProgressIndicator())
              : RefreshIndicator(
                  onRefresh: _loadItems,
                  child: ListView.builder(
                    physics: const AlwaysScrollableScrollPhysics(),
                    itemCount: _items.length,
                    itemBuilder: (_, index) {
                      final item = _items[index];
                      return Card(
                        margin: const EdgeInsets.symmetric(horizontal: 12, vertical: 6),
                        child: ListTile(
                          title: Text(item.title.isEmpty ? 'Untitled' : item.title),
                          subtitle: Column(
                            crossAxisAlignment: CrossAxisAlignment.start,
                            children: [
                              Text('[${item.type}] ${item.content}'),
                              const SizedBox(height: 4),
                              Text('Status: ${item.status}'),
                              if (item.dueAt != null && item.dueAt!.isNotEmpty)
                                Text('Due: ${DateFormat('y-MM-dd HH:mm').format(DateTime.tryParse(item.dueAt!) ?? DateTime.now())}'),
                              if (item.tags.isNotEmpty)
                                Text('Tags: ${item.tags.join(', ')}'),
                            ],
                          ),
                          trailing: Wrap(
                            spacing: 6,
                            children: [
                              IconButton(icon: const Icon(Icons.edit), onPressed: () => _editItem(item)),
                              IconButton(icon: const Icon(Icons.delete), onPressed: () => _deleteItem(item)),
                            ],
                          ),
                        ),
                      );
                    },
                  ),
                ),
        ),
      ],
    );
  }
}
