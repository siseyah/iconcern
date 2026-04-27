#!/usr/bin/env python3
"""
SVM Model Training Script for iconcern
Trains the SVM model using training data
"""

import os
import sys
import pickle
import re
import json
import random
from datetime import datetime

try:
    from sklearn.feature_extraction.text import TfidfVectorizer
    from sklearn.svm import SVC
    from sklearn.model_selection import train_test_split, StratifiedKFold, cross_val_score
    from sklearn.metrics import accuracy_score, classification_report, f1_score
    from sklearn.pipeline import Pipeline
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

def inject_text_noise(text, rng):
    """Create a slightly noisy variant to simulate real-world user typos/phrasing."""
    t = str(text)
    if not t:
        return t
    words = t.split()
    if len(words) >= 2 and rng.random() < 0.35:
        # Drop one random token
        drop_idx = rng.randrange(len(words))
        words = [w for i, w in enumerate(words) if i != drop_idx]
    if len(words) >= 3 and rng.random() < 0.30:
        # Swap neighboring words
        i = rng.randrange(len(words) - 1)
        words[i], words[i + 1] = words[i + 1], words[i]
    noisy = " ".join(words)
    if len(noisy) >= 6 and rng.random() < 0.35:
        # Remove one character in a random word (common typo)
        parts = noisy.split()
        wi = rng.randrange(len(parts))
        w = parts[wi]
        if len(w) > 3:
            ci = rng.randrange(len(w))
            parts[wi] = w[:ci] + w[ci + 1:]
        noisy = " ".join(parts)
    return noisy

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
    df = pd.DataFrame({"text": processed_texts, "label": labels})
    total_samples = len(df)
    print(f"Training samples loaded: {total_samples}")
    print()
    
    # Prepare arrays (raw text first; vectorizer must be fit on train only to avoid leakage)
    X_text = df["text"].tolist()
    y = np.array(df["label"].tolist())
    print(f"Samples prepared: {len(X_text)}")
    print(f"Number of categories: {len(set(labels))}")
    print(f"Categories: {', '.join(sorted(set(labels)))}")
    print()

    # Split data
    print("Splitting data into training and testing sets...")
    X_train_text, X_test_text, y_train, y_test = train_test_split(
        X_text, y, test_size=0.2, random_state=42, stratify=y
    )
    print(f"Training samples: {len(X_train_text)}")
    print(f"Testing samples: {len(X_test_text)}")
    print()

    # Vectorizer and model setup
    vectorizer = TfidfVectorizer(max_features=2000, ngram_range=(1, 3), min_df=2, max_df=0.95)
    model = SVC(kernel='linear', probability=True, random_state=42, C=1.0, class_weight='balanced')

    # Fit vectorizer on train only (leakage-safe), then train SVM
    print("Vectorizing training data...")
    X_train = vectorizer.fit_transform(X_train_text)
    X_test = vectorizer.transform(X_test_text)
    print(f"Feature matrix shape (train): {X_train.shape}")
    print()

    # Train SVM model
    print("Training SVM model (this may take a few moments)...")
    model.fit(X_train, y_train)
    print("Training completed!")
    print()
    
    # Evaluate
    print("Evaluating model performance...")
    y_pred = model.predict(X_test)
    accuracy = accuracy_score(y_test, y_pred)
    holdout_f1_macro = f1_score(y_test, y_pred, average='macro', zero_division=0)
    holdout_f1_weighted = f1_score(y_test, y_pred, average='weighted', zero_division=0)
    report_dict = classification_report(y_test, y_pred, output_dict=True, zero_division=0)
    print(f"Model Accuracy: {accuracy:.2%}")
    print(f"Holdout F1 Macro: {holdout_f1_macro:.4f}")
    print(f"Holdout F1 Weighted: {holdout_f1_weighted:.4f}")
    print()
    print("Detailed Classification Report:")
    print(classification_report(y_test, y_pred))
    print()

    # Robustness check: evaluate against noisy variants of test texts.
    rng = random.Random(42)
    X_test_noisy_text = [inject_text_noise(txt, rng) for txt in X_test_text]
    X_test_noisy = vectorizer.transform(X_test_noisy_text)
    y_pred_noisy = model.predict(X_test_noisy)
    robustness_f1_macro = f1_score(y_test, y_pred_noisy, average='macro', zero_division=0)
    robustness_f1_weighted = f1_score(y_test, y_pred_noisy, average='weighted', zero_division=0)
    print(f"Noisy Holdout F1 Macro:    {robustness_f1_macro:.4f}")
    print(f"Noisy Holdout F1 Weighted: {robustness_f1_weighted:.4f}")
    print()

    # Full-dataset (in-sample) check for visibility of all available samples.
    X_all = vectorizer.transform(X_text)
    y_pred_all = model.predict(X_all)
    full_dataset_f1_macro = f1_score(y, y_pred_all, average='macro', zero_division=0)
    full_dataset_f1_weighted = f1_score(y, y_pred_all, average='weighted', zero_division=0)
    print(f"Full Dataset F1 Macro:    {full_dataset_f1_macro:.4f}")
    print(f"Full Dataset F1 Weighted: {full_dataset_f1_weighted:.4f}")
    print()

    # Cross-validation F1 with pipeline so each fold fits vectorizer only on fold-train.
    cv = StratifiedKFold(n_splits=5, shuffle=True, random_state=42)
    cv_pipeline = Pipeline([
        ("tfidf", TfidfVectorizer(max_features=2000, ngram_range=(1, 3), min_df=2, max_df=0.95)),
        ("svc", SVC(kernel='linear', probability=False, random_state=42, C=1.0, class_weight='balanced')),
    ])
    cv_f1_macro_scores = cross_val_score(
        cv_pipeline,
        X_text,
        y,
        cv=cv,
        scoring='f1_macro'
    )
    cv_f1_weighted_scores = cross_val_score(
        cv_pipeline,
        X_text,
        y,
        cv=cv,
        scoring='f1_weighted'
    )
    cv_f1_macro_mean = float(np.mean(cv_f1_macro_scores))
    cv_f1_macro_std = float(np.std(cv_f1_macro_scores))
    cv_f1_weighted_mean = float(np.mean(cv_f1_weighted_scores))
    cv_f1_weighted_std = float(np.std(cv_f1_weighted_scores))
    print(f"CV F1 Macro (5-fold):    {cv_f1_macro_mean:.4f} ± {cv_f1_macro_std:.4f}")
    print(f"CV F1 Weighted (5-fold): {cv_f1_weighted_mean:.4f} ± {cv_f1_weighted_std:.4f}")
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

    # Save model performance metrics for dashboard/reporting.
    metrics_file = os.path.join(model_dir, 'model_metrics.json')
    metrics_payload = {
        "generated_at": datetime.now().isoformat(),
        "accuracy": float(accuracy),
        # Use noisy holdout F1 as primary "real-world-like" estimate.
        "f1_macro": float(robustness_f1_macro),
        "f1_weighted": float(robustness_f1_weighted),
        "holdout_f1_macro": float(holdout_f1_macro),
        "holdout_f1_weighted": float(holdout_f1_weighted),
        "noisy_holdout_f1_macro": float(robustness_f1_macro),
        "noisy_holdout_f1_weighted": float(robustness_f1_weighted),
        "cv_f1_macro_std": cv_f1_macro_std,
        "cv_f1_weighted_std": cv_f1_weighted_std,
        "precision_macro": float(report_dict.get("macro avg", {}).get("precision", 0.0)),
        "recall_macro": float(report_dict.get("macro avg", {}).get("recall", 0.0)),
        "dataset_samples": int(len(y)),
        "test_samples": int(len(y_test)),
        "holdout_samples": int(len(y_test)),
        "full_dataset_f1_macro": float(full_dataset_f1_macro),
        "full_dataset_f1_weighted": float(full_dataset_f1_weighted),
        "deduplicated_samples": int(len(y)),
    }
    with open(metrics_file, 'w', encoding='utf-8') as f:
        json.dump(metrics_payload, f, indent=2)
    
    print(f"Model saved to: {model_file}")
    print(f"Vectorizer saved to: {vectorizer_file}")
    print(f"Metrics saved to: {metrics_file}")
    print()
    print("=" * 60)
    print("Training completed successfully!")
    print("=" * 60)

if __name__ == "__main__":
    train_model()

