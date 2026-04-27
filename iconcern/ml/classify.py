#!/usr/bin/env python3
"""
Fully Enhanced iConcern Classifier
Supports Waray + English student concerns
Accurate multi-office classification and college detection
MIS Office now prioritized for all internet/network/system issues
"""

import sys
import os
import pickle
import re
import numpy as np
import csv

# -----------------------
# Paths
# -----------------------
MODEL_DIR = os.path.dirname(os.path.abspath(__file__))
MODEL_FILE = os.path.join(MODEL_DIR, 'svm_model.pkl')
VECTORIZER_FILE = os.path.join(MODEL_DIR, 'vectorizer.pkl')
TRAINING_DATA_FILE = os.path.join(MODEL_DIR, 'training_data.csv')

# -----------------------
# Office Categories
# -----------------------
CATEGORIES = [
    "MIS Office",
    "IMCO Office",
    "Registrar Office",
    "Internet Laboratory",
    "Cashier Office",
    "Maintenance Office",
    "ISSC Office",
    "Faculty Office"
]

# -----------------------
# College Keywords
# -----------------------
COLLEGE_KEYWORDS = {
    'COED': ['coed', 'college of education', 'education'],
    'CCIS': ['ccis', 'computing', 'computer science', 'it college', 'it courses'],
    'CCJS': ['ccjs', 'criminology', 'criminal justice', 'police science'],
    'COM': ['com', 'management', 'business', 'commerce'],
    'CEA': ['cea', 'engineering', 'architecture', 'civil', 'mechanical', 'electrical'],
    'CON': ['con', 'nursing', 'nurse', 'healthcare']
}

# -----------------------
# Preprocessing
# -----------------------
def preprocess_text(text):
    text = text.lower()
    replacements = {
        "wara": "waray",
        "san": "an",
        "cr": "comfort room",
        "kabaho": "mabaho",
        "narubat": "rubat",
        "kahugaw": "mahugaw",
        "kapaso": "mapaso"
    }
    # Replace whole-word tokens only (avoid collisions like "creation" containing "cr").
    # Using regex word boundaries keeps replacements deterministic for short abbreviations.
    for k, v in replacements.items():
        text = re.sub(r"\b" + re.escape(k) + r"\b", v, text)
    # Keep digits so inputs like "Form 137" still match registrar patterns
    text = re.sub(r'[^a-z0-9\s]', ' ', text)
    return " ".join(text.split())


# -----------------------
# Model loading / training
# -----------------------
_cached_model = None
_cached_vectorizer = None

def load_or_train_svm():
    """
    Loads the saved SVM+vectorizer if present.
    If model files are missing, trains from training_data.csv (first run only).
    """
    global _cached_model, _cached_vectorizer
    if _cached_model is not None and _cached_vectorizer is not None:
        return _cached_model, _cached_vectorizer

    if os.path.exists(MODEL_FILE) and os.path.exists(VECTORIZER_FILE):
        try:
            with open(MODEL_FILE, "rb") as f:
                _cached_model = pickle.load(f)
            with open(VECTORIZER_FILE, "rb") as f:
                _cached_vectorizer = pickle.load(f)
            return _cached_model, _cached_vectorizer
        except Exception:
            pass

    if not os.path.exists(TRAINING_DATA_FILE):
        return None, None

    try:
        from sklearn.feature_extraction.text import TfidfVectorizer
        from sklearn.svm import SVC
        import numpy as _np

        texts = []
        labels = []
        with open(TRAINING_DATA_FILE, "r", encoding="utf-8") as f:
            reader = csv.DictReader(f)
            for row in reader:
                concern_text = row.get("concern_text", "") or ""
                label = row.get("label", "") or ""
                if not label:
                    continue
                texts.append(preprocess_text(concern_text))
                labels.append(label)

        if len(texts) < 20 or len(set(labels)) < 2:
            return None, None

        vectorizer = TfidfVectorizer(max_features=2000, ngram_range=(1, 3), min_df=2, max_df=0.95)
        X = vectorizer.fit_transform(texts)
        y = _np.array(labels)

        model = SVC(kernel="linear", probability=True, random_state=42, C=1.0)
        model.fit(X, y)

        _cached_model = model
        _cached_vectorizer = vectorizer
        return _cached_model, _cached_vectorizer
    except Exception:
        return None, None

# -----------------------
# College Detection
# -----------------------
def detect_college(text):
    text = text.lower()
    for college, keywords in COLLEGE_KEYWORDS.items():
        for word in keywords:
            if word in text:
                return college
    return ""

# -----------------------
# Keyword Classifier (MIS priority)
# -----------------------
def simple_classify(text):
    text = text.lower()
    office_keywords = {
        "Maintenance Office": [
            "rubat","mabaho","mapaso","mahugaw","basurahan","sirado",
            "waray tubig","comfort room","classroom","room","electric","electricity","tv","hdmi",
            "repair","fix","broken","damage","plumbing","leak","fan","light"
        ],
        "Registrar Office": [
            "registrar","grades","grado","transcript","tor","records","academic record",
            "certificate","form 137","form 138","subject","course","units","enrollment",
            "registration","petition","schedule","classlist"
        ],
        "Cashier Office": [
            "cashier","payment","pay","bayad","baraydan","tuition","tuition fee",
            "school fee","receipt","resibo","refund","billing","fee clearance"
        ],
        "Faculty Office": [
            "teacher","professor","instructor","faculty","kaisog san instructor",
            "kabastos san instructor","rude instructor","unfair grading",
            "bias grading","late instructor","poor teaching","grading","lecture","exam"
        ],
        "ISSC Office": [
            "issc","student council","student government",
            "organization","student organization","club","student club",
            "student handbook","school event","student activity","orientation"
        ],
        "MIS Office": [
            "wifi","internet","network","server","portal","system","system error",
            "login","log in","cannot login","internet slow","wifi slow","network problem",
            "email","website","database","server down","cannot access","network outage",
            "internet disconnect","slow connection","system crash"
        ],
        "Internet Laboratory": [
            "internet lab","computer lab","lab computer",
            "laboratory computer","use computer lab","pc lab","id card","student id","school id"
        ],
        "IMCO Office": [
            "imco","school announcement","school information","school dissemination",
            "school notice","school update","memo","announcement board"
        ]
    }

    # Priority check: if any MIS keyword matches, force MIS Office
    mis_keywords = office_keywords["MIS Office"]
    if any(word in text for word in mis_keywords):
        confidence = min(0.95, 0.5 + sum(text.count(word) for word in mis_keywords)*0.05)
        return "MIS Office", confidence

    # Compute scores for other offices
    scores = {}
    for office, keywords in office_keywords.items():
        matches = sum(text.count(word) for word in keywords)
        if matches > 0:
            scores[office] = matches

    if scores:
        best_office = max(scores, key=scores.get)
        confidence = min(0.95, 0.5 + scores[best_office]*0.05)
        return best_office, confidence

    return "Registrar Office", 0.50

# -----------------------
# SVM Classifier
# -----------------------
def classify_concern(concern_text):
    try:
        processed = preprocess_text(concern_text)

        # Waray long-queue complaints (service inefficiency)
        queue_tokens = ["pila", "linya", "line", "queue"]
        long_queue_tokens = ["kadamo", "kahaba", "maiha"]
        cashier_tokens = ["cashier", "bayad", "baraydan", "resibo", "tuition", "payment"]
        registrar_tokens = ["registrar", "record", "records", "transcript", "tor", "cor", "enrollment", "registration", "papel"]
        if any(k in processed for k in queue_tokens) and any(k in processed for k in long_queue_tokens):
            if any(k in processed for k in cashier_tokens):
                return "Service Inefficiency - cashier office", 0.95
            if any(k in processed for k in registrar_tokens):
                return "Service Inefficiency - registrar office", 0.95

        model, vectorizer = load_or_train_svm()
        if model is None or vectorizer is None:
            return simple_classify(concern_text)

        vec = vectorizer.transform([processed])
        prediction = model.predict(vec)[0]
        proba = model.predict_proba(vec)[0]
        confidence = float(np.max(proba))
        return prediction, confidence
    except Exception:
        return simple_classify(concern_text)

# -----------------------
# Main
# -----------------------
def main():
    if len(sys.argv) < 2:
        print("")
        sys.exit(1)

    input_text = sys.argv[1]
    if os.path.exists(input_text):
        with open(input_text, "r", encoding="utf-8") as f:
            concern_text = f.read()
    else:
        concern_text = input_text

    if not concern_text.strip():
        print("")
        sys.exit(1)

    category, confidence = classify_concern(concern_text)
    college = detect_college(concern_text)
    print(f"{category}|{confidence:.2f}|{college}")

if __name__ == "__main__":
    main()