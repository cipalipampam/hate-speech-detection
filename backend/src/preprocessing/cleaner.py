"""
Text Cleaner Service — Modul 3: Preprocessing & Normalisasi Teks.

Deskripsi:
    Modul untuk membersihkan noise dari teks media sosial (X / Threads)
    menggunakan Regex & String sanitization agar siap diproses oleh
    IndoBERT Tokenizer.

Tahapan Pembersihan (dieksekusi berurutan di dalam `clean_text`):
    1. Hapus URL         : Menghapus tautan web (http/https/t.co/www).
    2. Hapus Mention     : Menghapus tanda '@' SAJA, kata di belakangnya dipertahankan.
    3. Hapus Hashtag     : Menghapus tanda '#' SAJA, kata di belakangnya dipertahankan.
    4. Hapus Karakter RT : Menghapus prefix "RT" retweet di awal teks.
    5. Hapus Non-ASCII   : Menghapus emoji, simbol, dan karakter unicode tidak standar
                           kecuali huruf (a-z A-Z), angka (0-9), dan spasi.
    6. Normalisasi Spasi : Memampatkan spasi berulang menjadi satu spasi & strip ujung.

Fungsi Publik:
    - clean_text(text: str) -> str
        Membersihkan satu string teks mentah.

    - case_folding(text: str) -> str
        Mengubah seluruh huruf menjadi huruf kecil (lowercase).

    - remove_duplicates(df: pd.DataFrame, subset: list | None = None) -> pd.DataFrame
        Menghapus baris duplikat dari DataFrame berdasarkan kolom yang ditentukan.
        Default subset: ['user_id', 'content'].
"""

import re
import logging
import pandas as pd

logger = logging.getLogger(__name__)

# ---------------------------------------------------------------------------
# Regex Pattern — Dikompilasi sekali untuk efisiensi
# ---------------------------------------------------------------------------

# 1. URL lengkap: http, https, ftp, www, dan shortlink t.co
_RE_URL = re.compile(
    r"(?:https?://|ftp://|www\.)\S+|t\.co/\S+",
    re.IGNORECASE,
)

# 2. Mention — hapus tanda '@' saja, pertahankan username
_RE_MENTION_SYMBOL = re.compile(r"@(?=\S)")

# 3. Hashtag — hapus tanda '#' saja, pertahankan kata
_RE_HASHTAG_SYMBOL = re.compile(r"#(?=\S)")

# 4. Prefix RT (Retweet) di awal kalimat
_RE_RT_PREFIX = re.compile(r"^\s*RT\s*:?\s*", re.IGNORECASE)

# 5. Hapus karakter non-alfanumerik & non-spasi (emoji, simbol, tanda baca, dll.)
#    Pertahankan huruf Latin (a-z A-Z), angka, dan spasi.
_RE_NON_ALPHANUM = re.compile(r"[^a-zA-Z0-9\s]")

# 6. Normalisasi multiple whitespace
_RE_MULTI_SPACE = re.compile(r"\s+")


# ---------------------------------------------------------------------------
# Fungsi Publik
# ---------------------------------------------------------------------------

def clean_text(text: str) -> str:
    """
    Membersihkan satu string teks mentah media sosial.

    Tahapan:
        1. Hapus URL
        2. Hapus simbol @ dari mention
        3. Hapus simbol # dari hashtag
        4. Hapus prefix RT
        5. Hapus karakter non-alfanumerik (emoji, simbol, tanda baca)
        6. Normalisasi spasi berlebih & strip

    Args:
        text (str): Teks mentah dari hasil scraping.

    Returns:
        str: Teks yang telah dibersihkan.
    """
    if not isinstance(text, str):
        return ""

    # 1. Hapus URL
    text = _RE_URL.sub(" ", text)

    # 2. Hapus simbol @ — pertahankan nama username
    text = _RE_MENTION_SYMBOL.sub("", text)

    # 3. Hapus simbol # — pertahankan kata hashtag
    text = _RE_HASHTAG_SYMBOL.sub("", text)

    # 4. Hapus prefix RT (Retweet)
    text = _RE_RT_PREFIX.sub("", text)

    # 5. Hapus karakter non-alfanumerik (emoji, simbol, tanda baca, dst.)
    text = _RE_NON_ALPHANUM.sub(" ", text)

    # 6. Normalisasi spasi
    text = _RE_MULTI_SPACE.sub(" ", text).strip()

    return text


def case_folding(text: str) -> str:
    """
    Mengubah seluruh huruf menjadi huruf kecil (lowercase / case folding).

    Args:
        text (str): Teks yang sudah dibersihkan.

    Returns:
        str: Teks dalam huruf kecil semua.
    """
    if not isinstance(text, str):
        return ""
    return text.lower()


def remove_duplicates(df: pd.DataFrame, subset=None) -> pd.DataFrame:
    """
    Menghapus baris duplikat dari DataFrame scraping.

    Strategi:
        - Memeriksa kombinasi kolom `subset` (default: ['user_id', 'content']).
        - Mempertahankan kemunculan pertama (keep='first').
        - Me-reset index agar berurutan kembali.

    Args:
        df     (pd.DataFrame): DataFrame hasil scraping mentah.
        subset (list)        : Daftar nama kolom untuk cek duplikat.
                               Default: ['user_id', 'content'].

    Returns:
        pd.DataFrame: DataFrame tanpa duplikat.
    """
    if df.empty:
        return df

    if subset is None:
        subset = ["user_id", "content"]

    # Hanya gunakan kolom subset yang benar-benar ada di DataFrame
    valid_subset = [col for col in subset if col in df.columns]
    if not valid_subset:
        logger.warning(
            "remove_duplicates: Tidak ada kolom subset yang valid di DataFrame. "
            "Melewati proses deduplikasi."
        )
        return df

    before = len(df)
    df = df.drop_duplicates(subset=valid_subset, keep="first").reset_index(drop=True)
    after = len(df)

    if before != after:
        logger.info(f"Deduplikasi: {before - after} baris duplikat dihapus ({before} → {after}).")

    return df
