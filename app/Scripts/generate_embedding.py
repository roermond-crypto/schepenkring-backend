#!/usr/bin/env python3
"""
Generate an image embedding using Gemini AI.
Simplified version of pinecone_sync.py — only generates the embedding vector.

Usage: python generate_embedding.py <GEMINI_API_KEY> <IMAGE_PATH>
Output: JSON {"embedding": [...3072 floats...], "description": "..."}
"""
import os
import sys
import json
import logging
from PIL import Image
from google import genai

logging.basicConfig(
    level=logging.INFO,
    format='%(asctime)s - %(levelname)s - %(message)s',
    stream=sys.stderr
)
logger = logging.getLogger(__name__)


def main():
    if len(sys.argv) != 3:
        logger.error("Usage: generate_embedding.py <GEMINI_API_KEY> <IMAGE_PATH>")
        print(json.dumps({"error": "Invalid arguments. Expected: GEMINI_API_KEY IMAGE_PATH"}))
        sys.exit(1)

    API_KEY = sys.argv[1]
    IMAGE_PATH = sys.argv[2]

    if not os.path.isfile(IMAGE_PATH):
        logger.error("Image not found: %s", IMAGE_PATH)
        print(json.dumps({"error": f"Image file not found: {IMAGE_PATH}"}))
        sys.exit(1)

    # Load image
    try:
        image = Image.open(IMAGE_PATH)
        logger.info("Image loaded, size: %s", image.size)
    except Exception as e:
        logger.exception("Failed to load image")
        print(json.dumps({"error": f"Image load failed: {str(e)}"}))
        sys.exit(1)

    # Configure Gemini client
    try:
        client = genai.Client(api_key=API_KEY)
        logger.info("Gemini client configured")
    except Exception as e:
        logger.exception("Gemini config failed")
        print(json.dumps({"error": f"Gemini config failed: {str(e)}"}))
        sys.exit(1)

    # Step 1: Generate a description of the boat
    try:
        logger.info("Generating boat description with Gemini Flash...")
        response = client.models.generate_content(
            model="models/gemini-2.5-flash",
            contents=[
                "Describe this boat image in detail, focusing on: hull shape, color, size, "
                "type of vessel (sailboat, motorboat, yacht, etc.), distinctive markings, "
                "name if visible, and any unique identifying features. Be concise but thorough.",
                image
            ]
        )
        description = response.text
        logger.info("Description generated (first 100 chars): %s", description[:100])
    except Exception as e:
        logger.exception("Description generation failed")
        print(json.dumps({"error": f"Description failed: {str(e)}"}))
        sys.exit(1)

    # Step 2: Generate embedding from the description
    try:
        logger.info("Generating embedding with gemini-embedding-001...")
        emb_response = client.models.embed_content(
            model="models/gemini-embedding-001",
            contents=[description]
        )
        embedding = emb_response.embeddings[0].values
        logger.info("Embedding generated, dimensions: %d", len(embedding))
    except Exception as e:
        logger.exception("Embedding generation failed")
        print(json.dumps({"error": f"Embedding failed: {str(e)}"}))
        sys.exit(1)

    # Output JSON for Laravel
    output = {
        "embedding": embedding,
        "description": description
    }
    print(json.dumps(output))


if __name__ == "__main__":
    main()
