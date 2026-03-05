#!/usr/bin/env python3
import os
import sys
import json
import logging
from PIL import Image
from google import genai

# Setup logging to stderr (Laravel captures this)
logging.basicConfig(
    level=logging.INFO,
    format='%(asctime)s - %(levelname)s - %(message)s',
    stream=sys.stderr
)
logger = logging.getLogger(__name__)

def main():
    if len(sys.argv) != 6:
        logger.error("Incorrect arguments. Expected 5, got %d", len(sys.argv)-1)
        print(json.dumps({"error": "Invalid arguments"}))
        sys.exit(1)

    API_KEY_GEMINI = sys.argv[1]
    # The next three arguments are no longer used (Pinecone key, index name)
    # but we keep them for backward compatibility with the controller.
    # API_KEY_PINECONE = sys.argv[2]   # unused
    # INDEX_NAME = sys.argv[3]         # unused
    IMAGE_PATH = sys.argv[4]
    PUBLIC_URL = sys.argv[5]

    logger.info("Processing: %s", IMAGE_PATH)

    if not os.path.isfile(IMAGE_PATH):
        logger.error("Image not found: %s", IMAGE_PATH)
        print(json.dumps({"error": "Image file not found"}))
        sys.exit(1)

    filename = os.path.basename(IMAGE_PATH)

    # Load image with PIL
    try:
        image = Image.open(IMAGE_PATH)
        logger.info("Image loaded, size: %s", image.size)
    except Exception as e:
        logger.exception("Failed to load image")
        print(json.dumps({"error": f"Image load failed: {str(e)}"}))
        sys.exit(1)

    # Configure Gemini client
    try:
        client = genai.Client(api_key=API_KEY_GEMINI)
        logger.info("Gemini client configured")
    except Exception as e:
        logger.exception("Gemini config failed")
        print(json.dumps({"error": f"Gemini config failed: {str(e)}"}))
        sys.exit(1)

    # Step 1: Generate a detailed description using Gemini Flash
    try:
        logger.info("Generating image description with Gemini Flash...")
        response = client.models.generate_content(
            model="models/gemini-2.5-flash",   # or "gemini-1.5-flash"
            contents=[
                "Describe this boat image in detail, focusing on visible parts, type of vessel, and any distinctive features. Be concise but thorough.",
                image
            ]
        )
        description = response.text
        logger.info("Description generated (first 100 chars): %s", description[:100])
    except Exception as e:
        logger.exception("Description generation failed")
        print(json.dumps({"error": f"Description failed: {str(e)}"}))
        sys.exit(1)

    # Step 2: Embed the description using the correct embedding model (3072 dimensions)
    try:
        logger.info("Embedding description with models/gemini-embedding-001")
        emb_response = client.models.embed_content(
            model="models/gemini-embedding-001",
            contents=[description]
        )
        embedding = emb_response.embeddings[0].values
        logger.info("Embedding generated, dimensions: %d", len(embedding))
    except Exception as e:
        logger.exception("Embedding failed")
        print(json.dumps({"error": f"Embedding failed: {str(e)}"}))
        sys.exit(1)

    # Output JSON for Laravel
    output = {
        "filename": filename,
        "public_url": PUBLIC_URL,
        "embedding": embedding,
        "description": description
    }
    print(json.dumps(output))

if __name__ == "__main__":
    main()