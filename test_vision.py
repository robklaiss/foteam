from google.cloud import vision
import os

def test_vision_connection():
    try:
        # Create a client
        client = vision.ImageAnnotatorClient()
        
        # Use a sample image URL to test
        image = vision.Image()
        image.source.image_uri = "https://storage.googleapis.com/cloud-samples-data/vision/text/screen.jpg"
        
        # Perform text detection
        response = client.text_detection(image=image)
        
        if response.error.message:
            print(f"Error: {response.error.message}")
            return False
            
        texts = response.text_annotations
        if texts:
            print("Success! Vision API connection is working.")
            print(f"Sample text detected: {texts[0].description[:100]}...")
            return True
        else:
            print("No text detected in the sample image.")
            return True
            
    except Exception as e:
        print(f"Error connecting to Vision API: {str(e)}")
        return False

if __name__ == "__main__":
    print("Testing Vision API connection...")
    test_vision_connection()
