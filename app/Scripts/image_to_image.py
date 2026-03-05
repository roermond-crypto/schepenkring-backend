import sys
import json
import google.generativeai as genai
from pinecone import Pinecone

# Inputs
API_KEY_GEMINI = sys.argv[1]
API_KEY_PINECONE = sys.argv[2]
INDEX_NAME = sys.argv[3]
IMAGE_PATH = sys.argv[4]

genai.configure(api_key=API_KEY_GEMINI)
pc = Pinecone(api_key=API_KEY_PINECONE)
index = pc.Index(INDEX_NAME)

def search_by_image():
    try:
        # 1. Convert Query Image to Vector
        img_file = genai.upload_file(path=IMAGE_PATH)
        result = genai.embed_content(
            model="models/multimodalembedding@001",
            content=img_file
        )
        query_vector = result['embedding']

        # 2. Search Pinecone for top 5 visual matches
        search_results = index.query(
            vector=query_vector,
            top_k=5,
            include_metadata=True
        )

        # 3. Format results
        matches = []
        for match in search_results['matches']:
            matches.append({
                'score': round(match['score'], 4),
                'url': match['metadata']['url']
            })
        
        print(json.dumps(matches))
    except Exception as e:
        print(json.dumps({"error": str(e)}))
        sys.exit(1)

if __name__ == "__main__":
    search_by_image()