#!/usr/bin/env python3
import sys
import json
import google.generativeai as genai
from pinecone import Pinecone
from PIL import Image
import requests
from io import BytesIO
import logging

logging.basicConfig(level=logging.INFO)
logger = logging.getLogger(__name__)

def detect_language(text):
    common_dutch = ['de', 'het', 'een', 'van', 'en', 'is', 'voor', 'op', 'met']
    words = text.lower().split()
    score = sum(1 for w in words if w in common_dutch)
    return 'nl' if score > 0 else 'en'

def analyze():
    try:
        API_KEY_GEMINI = sys.argv[1]
        API_KEY_PINECONE = sys.argv[2]
        INDEX_NAME = sys.argv[3]
        QUERY_TEXT = sys.argv[4]

        genai.configure(api_key=API_KEY_GEMINI)
        pc = Pinecone(api_key=API_KEY_PINECONE)
        index = pc.Index(INDEX_NAME)

        user_lang = detect_language(QUERY_TEXT)

        # Embed query
        emb = genai.embed_content(
            model="models/multimodalembedding@001",
            content=QUERY_TEXT,
            task_type="retrieval_query"
        )
        query_vector = emb['embedding']

        # Search Pinecone
        results = index.query(
            vector=query_vector,
            top_k=3,
            include_metadata=True
        )

        matches = results.get('matches', [])
        images = []
        urls = []

        for match in matches:
            url = match['metadata']['url']
            try:
                resp = requests.get(url, timeout=10)
                img = Image.open(BytesIO(resp.content))
                images.append(img)
                urls.append(url)
            except Exception as e:
                logger.warning(f"Could not load {url}: {e}")

        # Prepare prompt with language instruction
        lang_instruction = 'Dutch' if user_lang == 'nl' else 'English'

        if not images:
            prompt = f"""
You are a professional marine mechanic. A user asked: "{QUERY_TEXT}"

No matching reference images were found. Please answer based on your general knowledge of boat parts and mechanics.
Be concise, technical, and respond in {lang_instruction}.
"""
            model = genai.GenerativeModel('gemini-1.5-flash')
            response = model.generate_content(prompt)
            answer = response.text
        else:
            prompt = f"""
You are a professional marine mechanic. A user asked: "{QUERY_TEXT}"

I have provided {len(images)} reference images from our boat database.
Identify visible boat parts and explain their function. Be concise, technical, and respond in {lang_instruction}.
"""
            model = genai.GenerativeModel('gemini-1.5-flash')
            response = model.generate_content([prompt] + images)
            answer = response.text if response.text else "I couldn't analyze the images."

        print(json.dumps({
            "answer": answer,
            "references": urls
        }))

    except Exception as e:
        logger.exception("Fatal error")
        print(json.dumps({
            "error": str(e),
            "answer": "Boat analysis is temporarily unavailable.",
            "references": []
        }))
        sys.exit(1)

if __name__ == "__main__":
    analyze()