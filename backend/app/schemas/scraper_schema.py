"""
Pydantic Schema untuk Modul Scraping.

Model yang didefinisikan:
    - ScrapeRequest:
        - platform: Literal["x", "threads"]
        - keyword: str
        - max_posts: int = 50
    - ScrapePostItem:
        - id: str
        - platform: str
        - author: str
        - created_at: str
        - raw_text: str
    - ScrapeResponse:
        - status: str
        - total_scraped: int
        - data: List[ScrapePostItem]
"""

# TODO: Definisikan Pydantic BaseModel untuk request & response scraping
