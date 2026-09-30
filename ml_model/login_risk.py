"""
Random Forest login risk scorer — OSWD Complaint System
"""

import json
import os
import pickle
import sys

import numpy as np
from sklearn.ensemble import RandomForestClassifier
from sklearn.model_selection import train_test_split
from sklearn.metrics import accuracy_score

FEATURE_NAMES = [
    'hour',
    'day_of_week',
    'failed_attempts_user_15m',
    'failed_attempts_ip_15m',
    'is_new_ip_for_user',
    'is_new_user_agent',
    'account_age_days',
    'role_student',
]

LABELS = ['low', 'medium', 'high']


class LoginRiskModel:
    def __init__(self):
        base = os.path.dirname(os.path.abspath(__file__))
        self.model_dir = os.path.join(base, 'models')
        self.model_path = os.path.join(self.model_dir, 'login_risk.pkl')
        self.clf = RandomForestClassifier(
            n_estimators=120,
            max_depth=8,
            random_state=42,
            class_weight='balanced',
        )

    def _vectorize(self, features):
        row = [float(features.get(name, 0)) for name in FEATURE_NAMES]
        return np.array([row])

    def train(self):
        rng = np.random.default_rng(42)
        rows = []
        y = []

        for _ in range(800):
            hour = int(rng.integers(0, 24))
            dow = int(rng.integers(0, 7))
            fail_user = int(rng.integers(0, 3))
            fail_ip = int(rng.integers(0, 4))
            new_ip = int(rng.integers(0, 2))
            new_ua = int(rng.integers(0, 2))
            age = int(rng.integers(1, 800))
            role_student = int(rng.integers(0, 2))
            rows.append([hour, dow, fail_user, fail_ip, new_ip, new_ua, age, role_student])
            y.append('low')

        for _ in range(200):
            rows.append([
                int(rng.integers(0, 24)),
                int(rng.integers(0, 7)),
                int(rng.integers(1, 4)),
                int(rng.integers(2, 8)),
                1,
                int(rng.integers(0, 2)),
                int(rng.integers(10, 400)),
                1,
            ])
            y.append('medium')

        for _ in range(150):
            rows.append([
                int(rng.integers(0, 24)),
                int(rng.integers(0, 7)),
                int(rng.integers(5, 15)),
                int(rng.integers(10, 30)),
                1,
                1,
                int(rng.integers(1, 60)),
                int(rng.integers(0, 2)),
            ])
            y.append('high')

        X = np.array(rows)
        X_train, X_test, y_train, y_test = train_test_split(
            X, y, test_size=0.2, random_state=42, stratify=y
        )
        self.clf.fit(X_train, y_train)
        acc = accuracy_score(y_test, self.clf.predict(X_test))

        os.makedirs(self.model_dir, exist_ok=True)
        with open(self.model_path, 'wb') as f:
            pickle.dump({'model': self.clf, 'features': FEATURE_NAMES, 'version': '1.0'}, f)

        return {'success': True, 'accuracy': float(acc), 'model_path': self.model_path}

    def load(self):
        if not os.path.isfile(self.model_path):
            return False
        with open(self.model_path, 'rb') as f:
            payload = pickle.load(f)
        self.clf = payload['model']
        return True

    def predict(self, features):
        X = self._vectorize(features)
        pred = self.clf.predict(X)[0]
        probs = self.clf.predict_proba(X)[0]
        classes = list(self.clf.classes_)
        prob_map = {cls: float(p) for cls, p in zip(classes, probs)}
        score = float(prob_map.get('high', 0.0) + prob_map.get('medium', 0.0) * 0.5)
        return {
            'label': pred,
            'score': score,
            'probabilities': prob_map,
            'model_version': '1.0',
        }


def main():
    if len(sys.argv) < 2:
        print(json.dumps({'error': 'missing command'}))
        return

    cmd = sys.argv[1]
    model = LoginRiskModel()

    if cmd == 'train':
        print(json.dumps(model.train()))
        return

    if cmd == 'predict':
        raw = sys.argv[2] if len(sys.argv) > 2 else sys.stdin.read()
        try:
            features = json.loads(raw)
        except json.JSONDecodeError:
            print(json.dumps({'error': 'invalid json'}))
            return

        if model.load():
            print(json.dumps(model.predict(features)))
        else:
            print(json.dumps(rule_fallback(features)))
        return

    print(json.dumps({'error': 'unknown command'}))


def rule_fallback(features):
    fail_ip = int(features.get('failed_attempts_ip_15m', 0))
    fail_user = int(features.get('failed_attempts_user_15m', 0))
    new_ip = int(features.get('is_new_ip_for_user', 0))
    new_ua = int(features.get('is_new_user_agent', 0))

    if fail_ip >= 10 or fail_user >= 6:
        label = 'high'
    elif fail_ip >= 4 or fail_user >= 3 or (new_ip and new_ua):
        label = 'medium'
    else:
        label = 'low'

    score_map = {'low': 0.15, 'medium': 0.55, 'high': 0.9}
    return {
        'label': label,
        'score': score_map[label],
        'probabilities': {label: 1.0},
        'model_version': 'rules',
        'note': 'fallback rules — train model with login_risk.py train',
    }


if __name__ == '__main__':
    main()
