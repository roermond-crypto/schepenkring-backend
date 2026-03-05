import sys
import json
import google.generativeai as genai
from pinecone import Pinecone

# Inputs
API_KEY_GEMINI = sys.argv[1]
API_KEY_PINECONE = sys.argv[2]
INDEX_NAME = sys.argv[3]
QUERY_TEXT = sys.argv[4]

genai.configure(api_key=API_KEY_GEMINI)
pc = Pinecone(api_key=API_KEY_PINECONE)
index = pc.Index(INDEX_NAME)

def search():
    try:
        # 1. Convert Text Query to Vector (MUST use the same model as the images)
        # For multimodal RAG, we embed the text into the same 1408 space
        result = genai.embed_content(
            model="models/multimodalembedding@001",
            content=QUERY_TEXT,
            task_type="retrieval_query"
        )
        query_vector = result['embedding']

        # 2. Search Pinecone
        search_results = index.query(
            vector=query_vector,
            top_k=5,              # Number of images to return
            include_metadata=True # We need the URLs from the metadata
        )

        # 3. Format results for Laravel
        matches = []
        for match in search_results['matches']:
            matches.append({
                'id': match['id'],
                'score': match['score'],
                'url': match['metadata']['url']
            })
        
        print(json.dumps(matches))
    except Exception as e:
        print(json.dumps({"error": str(e)}))
        sys.exit(1)

if __name__ == "__main__":
    search()