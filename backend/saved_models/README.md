# Direktori Model IndoBERT Fine-Tuned

Letakkan artefak model IndoBERT yang telah selesai di-fine-tune dari Google Colab di folder ini:
1. `config.json`
2. `pytorch_model.bin` (atau bobot model PyTorch `.pt`)
3. `tokenizer_config.json`
4. `vocab.txt`
5. `special_tokens_map.json`

> **Catatan:** Model ini akan dimuat secara otomatis oleh `src/classification/model_loader.py` saat backend berjalan.
