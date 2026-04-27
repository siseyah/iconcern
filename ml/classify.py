#!/usr/bin/env python3
"""
Improved iConcern Classifier (Final Version)
Fix: Cashier vs Registrar accuracy + Waray support
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
# Preprocessing
# -----------------------
def preprocess_text(text):
    text = text.lower()

    replacements = {
        "wara": "waray",
        "san": "an",
        "cr": "comfort room",
        "id": "id card",
        "wifi": "internet",
        "log in": "login",
        "mahugaw": "dirty",
        "mabaho": "bad smell",
        "baraydan": "payment",
        "resibo": "receipt",
        "marka": "grade",
        "grado": "grade",
        "waray tubig": "no water",
        "naruba": "broken",
        "narubat": "broken",
        "guba": "broken",
        "waray": "none",
        "di": "not",
        "diri": "not",
        "kulang": "missing",
        "sayop": "wrong",
        "sakto": "correct",
        "pakyas": "failed",
        "hinay": "slow",
        "awol": "absent",
        "eskwela": "school",
        "kwarto": "room",
        "problema": "problem"
    }

    for k, v in replacements.items():
        text = re.sub(r"\b" + re.escape(k) + r"\b", v, text)

    text = re.sub(r'[^a-z0-9\s]', ' ', text)
    return " ".join(text.split())

# -----------------------
# Stage 1 Classifier
# -----------------------
def classify_stage1(text):
    text = text.lower()

    # Hard guard: registrar/records concerns should never fall to IMCO.
    registrar_guard = [
        "registrar", "tor", "cor", "transcript", "record", "records", "grade encoding",
        "encoded", "encoding", "enrollment", "registration", "add drop", "add", "drop",
        "form 137", "form 138", "credentials", "document", "documents", "requirement",
        "requirements", "school requirement", "wrong name", "papel"
    ]
    if any(k in text for k in registrar_guard):
        return "Administrative Concern"

    # Specific registrar-routed categories requested by user flow.
    sectioning_guard = ["wrong section", "sayop nga section", "incorrect section", "wrong block", "wrong class section"]
    if any(k in text for k in sectioning_guard):
        return "Sectioning Issue"

    schedule_guard = ["class schedule", "schedule issue", "wrong schedule", "schedule conflict", "conflict schedule", "no schedule", "kulang schedule", "schedule ko"]
    if any(k in text for k in schedule_guard):
        return "Class Schedule Issue"

    # Hard guard: sexual/gender-based harassment by instructor should route to GAD.
    sexual_gender_kws = [
        "sexual harassment", "sexual harassed", "sexually harassed", "sexual abuse", "molest", "molested", "gender based", "gender-based", "manyak",
        "malicious touch", "inappropriate touch", "inappropriate message",
        "catcalling", "catcall", "rape joke", "sexist", "misogyn"
    ]
    instructor_kws = ["instructor", "teacher", "professor", "faculty", "sir", "maam"]
    if any(k in text for k in sexual_gender_kws) and any(k in text for k in instructor_kws):
        return "Sexual/Gender-based"

    stage1_keywords = {
        "Academic Concern": [
            "grade", "subject", "transcript", "record", "records", "enrollment",
            "registration", "schedule", "form 137", "form 138", "certificate",
            "professor", "teacher", "faculty", "instructor", "exam", "marka", "grado",
            "lesson", "discussion", "attendance", "reconsider", "grading",
            "tor", "cor", "encoded", "encoding", "add", "drop",
            "quiz", "recitation", "class standing", "final grade", "midterm",
            "subject load", "class adviser", "absent teacher", "unfair grade"
        ],
        "Class Schedule Issue": [
            "class schedule", "schedule issue", "wrong schedule", "schedule conflict",
            "conflict schedule", "no schedule", "kulang schedule", "schedule ko"
        ],
        "Sectioning Issue": [
            "wrong section", "sayop nga section", "incorrect section", "wrong block", "wrong class section"
        ],
        "Financial Concern": [
            "payment", "tuition", "fee", "receipt", "billing", "refund",
            "cashier", "balance", "baraydan", "bayad", "resibo", "utang", "kwarta",
            "downpayment", "assessment", "scholarship", "discount", "penalty", "overpayment"
        ],
        "Facility Concern": [
            "broken", "facility", "comfort room", "repair", "leak", "plumbing",
            "electric", "electricity", "water", "no water", "toilet", "dirty",
            "bad smell", "fan", "light", "chair", "desk", "room",
            "aircon", "ceiling", "door", "window", "blackboard", "projector", "flood"
        ],
        "Sexual/Gender-based": [
            "sexual harassment", "sexual harassed", "sexually harassed", "sexual abuse", "molest", "molested", "gender based", "gender-based", "manyak",
            "malicious touch", "inappropriate touch", "inappropriate message",
            "catcalling", "catcall", "rape joke", "sexist", "misogyn",
            "instructor", "teacher", "professor", "faculty", "sir", "maam"
        ],
        "Safety & Discipline": [
            "guard", "security", "campus security", "threat", "unsafe", "danger",
            "weapon", "violence", "intruder", "misconduct", "discipline",
            "disciplinary", "violation", "fighting", "cheating", "vandalism",
            "emotional", "trauma", "traumatized", "anxiety", "depression",
            "stress", "panic", "mental health", "guidance", "counseling",
            "argument", "misunderstanding", "verbal conflict", "physical fight",
            "bullying", "cyberbullying", "harassment between students", "emotional distress",
            "gwardya", "delikado", "hadlok", "pasaway", "kabaraka", "nalulumo",
            "paglalis", "pakigbais", "panmiminsaray", "ginsisigawan", "masubo", "ginkukulbaan"
        ],
        "Technical/IT": [
            "internet", "network", "wifi", "system", "portal", "login", "server",
            "website", "email", "database", "computer", "laptop", "technical", "it",
            "forgot password", "reset password", "account locked", "cannot access",
            "slow connection", "lag", "bug", "error", "not loading"
        ],
        "Student Affairs Concern": [
            "issc", "student council", "student government", "organization", "club",
            "event", "activity", "orientation", "student affairs",
            "discipline", "bullying", "harassment", "student welfare", "guidance"
        ],
        "Administrative Concern": [
            "clearance", "document", "request", "administrative", "approval",
            "verification", "form", "registrar", "office process",
            "permit", "endorsement", "signature", "office transaction"
        ],
        "General Inquiry / School Updates": [
            "announcement", "memo", "notice", "update", "school update", "information", "imco",
            "when", "where", "how", "what time", "calendar", "holiday", "suspension"
        ],
    }

    scores = {}
    for label, kws in stage1_keywords.items():
        score = 0
        for kw in kws:
            if kw in text:
                score += 2 if " " in kw else 1
        if score > 0:
            scores[label] = score

    if not scores:
        return "General Inquiry / School Updates"

    return max(scores, key=scores.get)

# -----------------------
# Load SVM
# -----------------------
_cached_model = None
_cached_vectorizer = None

def load_or_train_svm():
    global _cached_model, _cached_vectorizer

    if _cached_model and _cached_vectorizer:
        return _cached_model, _cached_vectorizer

    if os.path.exists(MODEL_FILE) and os.path.exists(VECTORIZER_FILE):
        try:
            with open(MODEL_FILE, "rb") as f:
                _cached_model = pickle.load(f)
            with open(VECTORIZER_FILE, "rb") as f:
                _cached_vectorizer = pickle.load(f)
            return _cached_model, _cached_vectorizer
        except:
            return None, None

    return None, None

# -----------------------
# Keywords (ONLY Cashier & Registrar improved)
# -----------------------
OFFICE_KEYWORDS = {

    "MIS Office": [
        "internet", "network", "wifi", "connection",
        "server", "system", "portal", "login", "cannot login",
        "email", "database", "website", "technical", "it support",
        "reset password", "account locked", "not loading", "error", "bug", "slow connection",
        "hinay internet", "waray internet", "di maka login", "diri maka login"
    ],

    "Registrar Office": [
        # English
        "transcript", "records", "grades", "enrollment",
        "registration", "petition", "schedule",
        "form 137", "form 138", "certificate", "tor", "cor", "credentials",
        "add drop", "wrong section", "wrong name", "encoded grade",

        # Waray
        "grado", "marka", "record", "enrol",
        "subject ko", "schedule ko", "papel", "sayop nga section", "sayop nga record",
        "kuha transcript", "kuha record", "kuha tor", "kuha cor"
    ],

    "Cashier Office": [
        # English
        "payment", "pay", "paid", "tuition",
        "receipt", "billing", "balance", "downpayment", "assessment", "discount",

        # Waray
        "bayad", "baraydan", "resibo",
        "kwarta", "baydan", "utang", "balanse", "kwarta ko", "bayad ko"
    ],

    "Internet Laboratory": [
        "computer", "lab", "laboratory",
        "student id", "id card"
    ],

    "Maintenance Office": [
        "comfort room", "dirty", "broken",
        "repair", "electric", "water", "no water", "bad smell",
        "plumbing", "leak", "fan", "light", "chair", "desk",
        "mahugaw", "mabaho", "waray tubig", "naruba", "guba", "nag tutulo", "init nga room"
    ],

    "GAD Office": [
        "sexual harassment", "sexual harassed", "sexually harassed", "sexual abuse", "molest", "molested", "gender based", "gender-based", "manyak",
        "malicious touch", "inappropriate touch", "inappropriate message",
        "catcalling", "catcall", "rape joke", "sexist", "misogyn",
        "instructor harassment", "teacher harassment", "professor harassment",
        "instructor", "teacher", "professor", "faculty", "sir", "maam"
    ],

    "Campus Security Office": [
        "guard", "security", "campus security", "threat", "unsafe", "danger",
        "weapon", "violence", "intruder", "trespass", "threat between students", "unsafe environment",
        "gwardya", "delikado", "hadlok", "panarhog", "waray safety"
    ],

    "Student Affairs Office": [
        "misconduct", "discipline", "disciplinary", "violation", "student misconduct",
        "argument", "misunderstanding", "verbal conflict", "physical fight", "fight", "fighting",
        "bullying", "cyberbullying", "harassment between students", "harassment",
        "cheating", "vandalism", "pasaway", "away", "pangabuso", "paglalis", "pakigbais", "panmiminsaray"
    ],

    "Guidance Office": [
        "emotional distress", "emotional", "trauma", "traumatized", "anxiety", "depression", "stress", "panic",
        "mental health", "guidance", "counseling", "counselling", "kabaraka", "nalulumo", "na trauma", "masubo", "ginkukulbaan"
    ],

    "IMCO Office": [
        "announcement", "memo", "notice", "school update", "advisory",
        "information", "dissemination", "calendar", "suspension"
    ],

    "ISSC Office": [
        "student council", "organization", "event", "activity", "bullying", "harassment", "guidance"
    ],

    "Faculty Office": [
        "teacher", "professor", "instructor", "grading", "exam", "lesson",
        "discussion", "attendance", "class performance", "unfair grade", "reconsider"
    ]
}

# -----------------------
# Keyword Classifier (FIXED)
# -----------------------
def keyword_classify(text):
    text = preprocess_text(text)

    scores = {}

    for office, keywords in OFFICE_KEYWORDS.items():
        score = 0
        for kw in keywords:
            if kw in text:
                if " " in kw:
                    score += 3
                else:
                    score += 1
        if score > 0:
            scores[office] = score

    # -------------------------------
    # 🎯 CASHIER vs REGISTRAR FIX
    # -------------------------------
    cashier_words = [
        "payment", "pay", "paid", "tuition", "fee",
        "receipt", "billing", "balance",
        "bayad", "baraydan", "resibo",
        "kwarta", "baydan", "utang", "balanse"
    ]

    registrar_words = [
        "transcript", "records", "grades", "enrollment",
        "registration", "subject", "schedule", "petition",
        "grado", "marka", "record", "enrol",
        "subject ko", "schedule ko", "papel"
    ]

    cashier_score = sum(1 for w in cashier_words if w in text)
    registrar_score = sum(1 for w in registrar_words if w in text)

    if cashier_score > 0 and registrar_score == 0:
        return "Cashier Office", 0.92

    if registrar_score > 0 and cashier_score == 0:
        return "Registrar Office", 0.92

    if cashier_score > 0 and registrar_score > 0:
        if any(w in text for w in ["bayad", "payment", "tuition", "baraydan"]):
            return "Cashier Office", 0.95
        else:
            return "Registrar Office", 0.90

    # -------------------------------
    # Default
    # -------------------------------
    if not scores:
        return "Registrar Office", 0.50

    best = max(scores, key=scores.get)
    confidence = min(0.95, 0.5 + scores[best] * 0.05)

    return best, confidence

def _keyword_hits(text, keywords):
    return sum(1 for kw in keywords if kw in text)

def _scaled_confidence(text, keywords, base=0.78, step=0.03, cap=0.98):
    hits = _keyword_hits(text, keywords)
    return min(cap, base + (hits * step))

# -----------------------
# Hybrid Classifier
# -----------------------
def classify_concern(text):
    processed = preprocess_text(text)

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

    # Priority guards to improve routing accuracy on critical flows.
    # These run before Stage 1 to prevent generic categories from stealing matches.
    id_kws = [
        "id", "id card", "student id", "school id", "identification card",
        "id processing", "id creation", "id making", "id application",
        "id claim", "id release", "id issue"
    ]
    if any(k in processed for k in id_kws):
        return "Internet Laboratory", _scaled_confidence(processed, id_kws, 0.82, 0.03, 0.97)

    sexual_gender_kws = [
        "sexual harassment", "sexual harassed", "sexually harassed", "sexual abuse", "molest", "molested", "gender based", "gender-based", "manyak",
        "malicious touch", "inappropriate touch", "inappropriate message",
        "catcalling", "catcall", "rape joke", "sexist", "misogyn"
    ]
    instructor_kws = ["instructor", "teacher", "professor", "faculty", "sir", "maam"]
    if any(k in processed for k in sexual_gender_kws) and any(k in processed for k in instructor_kws):
        gad_kws = sexual_gender_kws + instructor_kws
        return "GAD Office", _scaled_confidence(processed, gad_kws, 0.84, 0.03, 0.98)

    safety_guidance_kws = [
        "emotional distress", "emotional", "trauma", "traumatized", "anxiety",
        "depression", "stress", "panic", "mental health", "guidance",
        "counseling", "counselling", "kabaraka", "nalulumo", "na trauma",
        "masubo", "ginkukulbaan"
    ]
    if any(k in processed for k in safety_guidance_kws):
        return "Guidance Office", _scaled_confidence(processed, safety_guidance_kws, 0.80, 0.03, 0.96)

    safety_misconduct_kws = [
        "misconduct", "discipline", "disciplinary", "violation", "student misconduct",
        "argument", "misunderstanding", "verbal conflict", "physical fight", "fight",
        "fighting", "bullying", "cyberbullying", "harassment between students",
        "harassment", "cheating", "vandalism", "pasaway", "away", "pangabuso",
        "paglalis", "pakigbais", "panmiminsaray", "ginsisigawan"
    ]
    if any(k in processed for k in safety_misconduct_kws):
        return "Student Affairs Office", _scaled_confidence(processed, safety_misconduct_kws, 0.80, 0.03, 0.96)

    safety_security_kws = [
        "guard", "security", "campus security", "threat between students", "threat",
        "unsafe environment", "unsafe", "danger", "weapon", "violence", "intruder",
        "trespass", "gwardya", "delikado", "hadlok", "panarhog", "waray safety"
    ]
    if any(k in processed for k in safety_security_kws):
        return "Campus Security Office", _scaled_confidence(processed, safety_security_kws, 0.80, 0.03, 0.96)

    stage1 = classify_stage1(processed)

    # Stage 1 guided high-priority routing (as requested pattern)
    if stage1 == "Facility Concern":
        kws = ["comfort room", "broken", "repair", "leak", "plumbing", "no water", "dirty", "bad smell", "fan", "light", "aircon"]
        return "Maintenance Office", _scaled_confidence(processed, kws, 0.80, 0.03, 0.97)
    if stage1 == "Sexual/Gender-based":
        kws = ["sexual harassment", "sexual harassed", "sexually harassed", "sexual abuse", "molest", "molested", "gender based", "gender-based", "manyak", "malicious touch", "inappropriate touch", "inappropriate message", "catcalling", "catcall", "rape joke", "sexist", "misogyn", "instructor", "teacher", "professor", "faculty", "sir", "maam"]
        return "GAD Office", _scaled_confidence(processed, kws, 0.84, 0.03, 0.98)
    if stage1 == "Financial Concern":
        kws = ["payment", "tuition", "fee", "receipt", "billing", "refund", "cashier", "balance", "bayad", "baraydan"]
        return "Cashier Office", _scaled_confidence(processed, kws, 0.80, 0.03, 0.97)
    if stage1 == "Academic Concern":
        records_keywords = [
            "registrar", "record", "records", "transcript", "tor", "cor",
            "enrollment", "registration", "encoded", "encoding", "system record",
            "document", "documents", "credentials", "add", "drop", "wrong section"
        ]
        teaching_keywords = [
            "teacher", "professor", "faculty", "instructor", "teaching", "lesson",
            "discussion", "grading", "grade", "grades", "marka", "grado", "reconsider", "attendance", "exam", "unfair"
        ]
        if any(k in processed for k in records_keywords):
            return "Registrar Office", _scaled_confidence(processed, records_keywords, 0.79, 0.028, 0.97)
        if any(k in processed for k in teaching_keywords):
            return "Faculty Office", _scaled_confidence(processed, teaching_keywords, 0.79, 0.028, 0.97)
        return "Registrar Office", 0.82
    if stage1 == "Class Schedule Issue":
        kws = ["class schedule", "schedule issue", "wrong schedule", "schedule conflict", "conflict schedule", "no schedule", "kulang schedule", "schedule ko"]
        return "Registrar Office", _scaled_confidence(processed, kws, 0.80, 0.03, 0.97)
    if stage1 == "Sectioning Issue":
        kws = ["wrong section", "sayop nga section", "incorrect section", "wrong block", "wrong class section"]
        return "Registrar Office", _scaled_confidence(processed, kws, 0.80, 0.03, 0.97)
    if stage1 == "Technical/IT":
        kws = ["internet", "network", "login", "portal", "system", "server", "website", "email", "database", "error", "bug"]
        return "MIS Office", _scaled_confidence(processed, kws, 0.80, 0.03, 0.97)
    if stage1 == "Student Affairs Concern":
        kws = ["issc", "student council", "organization", "event", "activity", "orientation", "bullying", "harassment", "guidance"]
        return "ISSC Office", _scaled_confidence(processed, kws, 0.78, 0.03, 0.96)
    if stage1 == "Safety & Discipline":
        security_kws = ["guard", "security", "campus security", "threat between students", "threat", "unsafe environment", "unsafe", "danger", "weapon", "violence", "intruder", "gwardya", "delikado", "hadlok", "waray safety"]
        misconduct_kws = ["misconduct", "discipline", "disciplinary", "violation", "student misconduct", "argument", "misunderstanding", "verbal conflict", "physical fight", "fight", "fighting", "bullying", "cyberbullying", "harassment between students", "harassment", "cheating", "vandalism", "pasaway", "away", "paglalis", "pakigbais", "panmiminsaray"]
        guidance_kws = ["emotional distress", "emotional", "trauma", "traumatized", "anxiety", "depression", "stress", "panic", "mental health", "guidance", "counseling", "counselling", "kabaraka", "nalulumo", "na trauma", "masubo", "ginkukulbaan"]

        if any(k in processed for k in guidance_kws):
            return "Guidance Office", _scaled_confidence(processed, guidance_kws, 0.78, 0.03, 0.96)
        if any(k in processed for k in misconduct_kws):
            return "Student Affairs Office", _scaled_confidence(processed, misconduct_kws, 0.78, 0.03, 0.96)
        return "Campus Security Office", _scaled_confidence(processed, security_kws, 0.78, 0.03, 0.96)
    if stage1 == "Administrative Concern":
        kws = ["registrar", "clearance", "document", "approval", "verification", "form", "requirements", "credentials"]
        return "Registrar Office", _scaled_confidence(processed, kws, 0.78, 0.03, 0.96)
    if stage1 == "General Inquiry / School Updates":
        kws = ["announcement", "memo", "notice", "school update", "advisory", "calendar", "suspension", "information"]
        # Only force IMCO when there are actual announcement/update signals.
        # Otherwise continue to model/keyword classifier to avoid repeated 0.50 outputs.
        if _keyword_hits(processed, kws) > 0:
            return "IMCO Office", _scaled_confidence(processed, kws, 0.50, 0.028, 0.95)

    model, vectorizer = load_or_train_svm()

    if model and vectorizer:
        try:
            vec = vectorizer.transform([processed])
            pred = model.predict(vec)[0]
            conf = float(np.max(model.predict_proba(vec)[0]))

            # Always return the model probability so confidence reflects
            # the actual prediction uncertainty instead of falling back
            # to a fixed keyword baseline (often 0.50).
            return pred, conf

        except:
            return keyword_classify(text)

    return keyword_classify(text)

# -----------------------
# MAIN
# -----------------------
def main():
    if len(sys.argv) < 2:
        print("Usage: python classify.py '<text>'")
        sys.exit(1)

    input_text = sys.argv[1]
    if os.path.exists(input_text):
        with open(input_text, "r", encoding="utf-8") as f:
            text = f.read()
    else:
        text = input_text

    if not text.strip():
        print("")
        sys.exit(1)

    category, confidence = classify_concern(text)

    print(f"{category}|{confidence:.2f}")

if __name__ == "__main__":
    main()