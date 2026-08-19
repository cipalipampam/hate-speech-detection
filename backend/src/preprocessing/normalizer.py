"""
Slang Word Normalizer Service — Modul 3: Preprocessing & Normalisasi Teks.

Deskripsi:
    Modul untuk mengubah kata-kata singkatan / bahasa gaul (slang) menjadi kata baku
    Bahasa Indonesia berdasarkan data mapping `storage/dictionaries/kamusalay.csv`.

    Kamus Kosakata Alay (Kamusalay) berisi 15.000+ entri pasangan kata:
        kolom 0 = kata slang / tidak baku (e.g. "bgt", "yg", "gw", "ga")
        kolom 1 = kata baku Bahasa Indonesia (e.g. "banget", "yang", "saya", "tidak")

    Referensi:
        Ibrohim, M.O., & Budi, I. (2019). Multi-label Hate Speech and Abusive Language
        Detection in Indonesian Twitter. ACL Anthology.

Class:
    - SlangNormalizer
        __init__(dictionary_path: str | Path | None = None)
            Memuat kamus ke dalam hash map Python (O(1) lookup per kata).
            Secara default menggunakan path dari `configs/config.py` (KAMUSALAY_PATH).

        normalize(text: str) -> str
            Melakukan tokenisasi kata dan mengganti kata slang jika ditemukan
            di kamus. Kata yang tidak ada di kamus dibiarkan apa adanya.

Fungsi Modul:
    - load_slang_dictionary(path) -> dict[str, str]
        Utilitas load kamus dari file CSV ke dictionary Python.

Contoh Transformasi:
    - "sy tdk suka sm pelayanannya yg lemot bgt" 
      -> "saya tidak suka sama pelayanannya yang lambat banget"
    - "gue udah bilang ke dia bwt dateng besok"
      -> "saya sudah bilang ke dia buat datang besok"
"""

import logging
from pathlib import Path

import pandas as pd

logger = logging.getLogger(__name__)

# ---------------------------------------------------------------------------
# Konstanta path default (fallback jika config tidak tersedia)
# ---------------------------------------------------------------------------

# parents[0] = preprocessing, parents[1] = src, parents[2] = backend (apps/backend)
_DEFAULT_DICT_PATH = Path(__file__).resolve().parents[2] / "storage" / "dictionaries" / "kamusalay.csv"


# ---------------------------------------------------------------------------
# Fungsi Utilitas
# ---------------------------------------------------------------------------

def load_slang_dictionary(path=None) -> dict:
    """
    Memuat kamus slang dari file CSV ke dalam dictionary Python.

    Format CSV yang didukung:
        - Tanpa header.
        - Kolom 0 : kata slang / tidak baku.
        - Kolom 1 : kata baku Bahasa Indonesia (pengganti).
        - Encoding: latin-1 (sesuai file kamusalay.csv asli).

    Args:
        path: Path ke file CSV kamus. Bisa str, Path, atau None.
              Jika None, menggunakan KAMUSALAY_PATH dari config (atau default fallback).

    Returns:
        dict[str, str]: Mapping {slang: baku} dalam huruf kecil semua.
    """
    # Resolusi path
    if path is None:
        try:
            from configs.config import KAMUSALAY_PATH
            resolved_path = Path(KAMUSALAY_PATH)
        except ImportError:
            resolved_path = _DEFAULT_DICT_PATH
    else:
        resolved_path = Path(path)

    if not resolved_path.exists():
        logger.error(f"File kamus tidak ditemukan: {resolved_path}")
        return {}

    try:
        df = pd.read_csv(
            resolved_path,
            header=None,
            encoding="latin-1",
            on_bad_lines="skip",
            dtype=str,
        )
        # Bersihkan kolom: strip whitespace, lowercase
        col_slang = df[0].str.strip().str.lower()
        col_baku  = df[1].str.strip().str.lower()

        # Buat dictionary hanya dari pasangan yang valid (keduanya non-NaN & non-kosong)
        mask = col_slang.notna() & col_baku.notna() & (col_slang != "") & (col_baku != "")
        slang_dict = dict(zip(col_slang[mask], col_baku[mask]))

        logger.info(f"Kamus slang berhasil dimuat: {len(slang_dict):,} entri dari '{resolved_path.name}'.")
        return slang_dict
    except Exception as e:
        logger.error(f"Gagal memuat kamus slang dari '{resolved_path}': {e}")
        return {}


# ---------------------------------------------------------------------------
# Class SlangNormalizer
# ---------------------------------------------------------------------------

class SlangNormalizer:
    """
    Normalisasi kata slang / bahasa gaul ke kata baku Bahasa Indonesia.

    Menggunakan strategi tokenisasi sederhana (split by spasi) dan pencarian
    O(1) pada hash map in-memory untuk kecepatan tinggi.

    Attributes:
        dictionary (dict[str, str]): Peta {slang_lower: kata_baku}.
        dictionary_size (int)      : Jumlah entri yang berhasil dimuat.
    """

    def __init__(self, dictionary_path=None):
        """
        Inisialisasi normalizer dengan memuat kamus ke memori.

        Args:
            dictionary_path: Path ke file kamusalay.csv.
                             Jika None, menggunakan path default dari config.
        """
        self.dictionary: dict = load_slang_dictionary(dictionary_path)
        self.dictionary_size: int = len(self.dictionary)

    def normalize(self, text: str) -> str:
        """
        Mengganti kata slang dalam teks dengan padanan kata baku.

        Strategi:
            1. Tokenisasi: pecah teks berdasarkan spasi.
            2. Lookup: tiap token di-cek ke kamus (lowercase).
            3. Replace: jika ditemukan, ganti; jika tidak, biarkan apa adanya.
            4. Re-join: gabungkan token kembali menjadi kalimat.

        Args:
            text (str): Teks yang sudah melalui clean_text() dan case_folding().

        Returns:
            str: Teks dengan kata slang digantikan kata baku.
        """
        if not isinstance(text, str) or not text.strip():
            return ""

        if not self.dictionary:
            return text

        tokens = text.split()
        normalized_tokens = [
            self.dictionary.get(token.lower(), token)
            for token in tokens
        ]
        return " ".join(normalized_tokens)

    def __repr__(self) -> str:
        return f"SlangNormalizer(dictionary_size={self.dictionary_size:,})"
