#!/usr/bin/env python3
"""
SVM Model Training Script for iconcern
Trains the SVM model using training data
"""

import os
import sys
import pickle
import re
from pathlib import Path

try:
    from sklearn.feature_extraction.text import TfidfVectorizer
    from sklearn.svm import SVC
    from sklearn.model_selection import train_test_split
    from sklearn.metrics import accuracy_score, classification_report
    import numpy as np
    import pandas as pd
except ImportError:
    print("Error: Required packages not installed. Install with: pip install scikit-learn pandas numpy")
    sys.exit(1)

def preprocess_text(text):
    """Preprocess text for training"""
    if not text:
        return ""
    text = text.lower()
    text = re.sub(r'[^a-z0-9\s]', '', text)
    text = ' '.join(text.split())
    return text

def load_training_data():
    """Load training data from CSV file"""
    model_dir = os.path.dirname(os.path.abspath(__file__))
    csv_file = os.path.join(model_dir, 'training_data.csv')
    
    if not os.path.exists(csv_file):
        print(f"Error: Training data file not found: {csv_file}")
        print("Please run generate_training_data.py first to create the dataset.")
        sys.exit(1)
    
    try:
        df = pd.read_csv(csv_file, encoding='utf-8')
        print(f"Loaded {len(df)} training entries from {csv_file}")
        return df['concern_text'].tolist(), df['label'].tolist()
    except Exception as e:
        print(f"Error loading training data: {e}")
        sys.exit(1)

def train_model():
    """Train the SVM model using 1000-entry dataset"""
    print("=" * 60)
    print("Training SVM Model for iconcern")
    print("=" * 60)
    print()
    
    # Load training data from CSV
    texts, labels = load_training_data()
    
    # Prepare data
    print("Preprocessing text data...")
    processed_texts = [preprocess_text(text) for text in texts]
    
    # Vectorize texts with enhanced features
    print("Vectorizing text data...")
    vectorizer = TfidfVectorizer(max_features=2000, ngram_range=(1, 3), min_df=2, max_df=0.95)
    X = vectorizer.fit_transform(processed_texts)
    y = np.array(labels)
    
    print(f"Feature matrix shape: {X.shape}")
    print(f"Number of categories: {len(set(labels))}")
    print(f"Categories: {', '.join(sorted(set(labels)))}")
    print()
    
    # Split data
    print("Splitting data into training and testing sets...")
    X_train, X_test, y_train, y_test = train_test_split(X, y, test_size=0.2, random_state=42, stratify=y)
    print(f"Training samples: {X_train.shape[0]}")
    print(f"Testing samples: {X_test.shape[0]}")
    print()
    
    # Train SVM model
    print("Training SVM model (this may take a few moments)...")
    model = SVC(kernel='linear', probability=True, random_state=42, C=1.0)
    model.fit(X_train, y_train)
    print("Training completed!")
    print()
    
    # Evaluate
    print("Evaluating model performance...")
    y_pred = model.predict(X_test)
    accuracy = accuracy_score(y_test, y_pred)
    print(f"Model Accuracy: {accuracy:.2%}")
    print()
    print("Detailed Classification Report:")
    print(classification_report(y_test, y_pred))
    print()
    
    # Save model and vectorizer
    model_dir = os.path.dirname(os.path.abspath(__file__))
    model_file = os.path.join(model_dir, 'svm_model.pkl')
    vectorizer_file = os.path.join(model_dir, 'vectorizer.pkl')
    
    print("Saving model files...")
    with open(model_file, 'wb') as f:
        pickle.dump(model, f)
    
    with open(vectorizer_file, 'wb') as f:
        pickle.dump(vectorizer, f)
    
    print(f"Model saved to: {model_file}")
    print(f"Vectorizer saved to: {vectorizer_file}")
    print()
    print("=" * 60)
    print("Training completed successfully!")
    print("=" * 60)

if __name__ == "__main__":
    train_model()

