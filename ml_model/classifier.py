"""
Random Forest Complaint Classifier
OSWD Complaint System - ML Module
"""

import sys
import json
import pickle
import os
import numpy as np
from sklearn.ensemble import RandomForestClassifier
from sklearn.feature_extraction.text import TfidfVectorizer
from sklearn.model_selection import train_test_split
from sklearn.metrics import accuracy_score, classification_report

class ComplaintClassifier:
    def __init__(self, model_path=None):
        if model_path is None:
            model_path = os.path.join(os.path.dirname(os.path.abspath(__file__)), 'models')
        self.model_path = model_path
        self.vectorizer = TfidfVectorizer(max_features=500, ngram_range=(1, 2))
        self.classifier = RandomForestClassifier(
            n_estimators=100,
            max_depth=10,
            random_state=42
        )
        self.categories = [
            'Academic Integrity Violation',
            'Unprofessional Behavior',
            'Institutional Rules Violation',
            'Teaching Standards Failure'
        ]
        
    def train(self, texts, labels):
        """Train the Random Forest model"""
        X = self.vectorizer.fit_transform(texts)
        X_train, X_test, y_train, y_test = train_test_split(
            X, labels, test_size=0.2, random_state=42
        )
        
        self.classifier.fit(X_train, y_train)
        y_pred = self.classifier.predict(X_test)
        
        accuracy = accuracy_score(y_test, y_pred)
        
        # Save model
        if not os.path.exists(self.model_path):
            os.makedirs(self.model_path)
            
        with open(os.path.join(self.model_path, 'vectorizer.pkl'), 'wb') as f:
            pickle.dump(self.vectorizer, f)
        with open(os.path.join(self.model_path, 'classifier.pkl'), 'wb') as f:
            pickle.dump(self.classifier, f)
            
        return {
            'accuracy': accuracy,
            'report': classification_report(y_test, y_pred, output_dict=True)
        }
    
    def load_model(self):
        """Load trained model"""
        try:
            with open(os.path.join(self.model_path, 'vectorizer.pkl'), 'rb') as f:
                self.vectorizer = pickle.load(f)
            with open(os.path.join(self.model_path, 'classifier.pkl'), 'rb') as f:
                self.classifier = pickle.load(f)
            return True
        except:
            return False
    
    def predict(self, text):
        """Predict complaint category"""
        X = self.vectorizer.transform([text])
        prediction = self.classifier.predict(X)[0]
        probabilities = self.classifier.predict_proba(X)[0]
        confidence = max(probabilities)
        
        return {
            'category': prediction,
            'confidence': float(confidence),
            'all_probabilities': {
                cat: float(prob) 
                for cat, prob in zip(self.classifier.classes_, probabilities)
            }
        }

def main():
    if len(sys.argv) < 2:
        print(json.dumps({'error': 'No command provided'}))
        return
    
    command = sys.argv[1]
    classifier = ComplaintClassifier()
    
    if command == 'predict':
        if len(sys.argv) < 3:
            print(json.dumps({'error': 'No text provided'}))
            return
        
        text = sys.argv[2]
        
        if not classifier.load_model():
            # Use default prediction if model not trained
            result = {
                'category': 'Unprofessional Behavior',
                'confidence': 0.5,
                'note': 'Using default prediction - model not trained'
            }
        else:
            result = classifier.predict(text)
        
        print(json.dumps(result))
    
    elif command == 'train':
        # Sample training data
        training_data = {
            'texts': [
                'Professor did not grade my exam fairly and showed favoritism',
                'Teacher harassed me and made discriminatory comments',
                'Faculty member violated academic policies and code of conduct',
                'Instructor neglected teaching responsibilities and poor communication',
                'Unfair grading and biased treatment in class',
                'Verbal abuse and intimidation by faculty',
                'Breach of university rules and regulations',
                'Poor teaching quality and inadequate feedback'
            ],
            'labels': [
                'Academic Integrity Violation',
                'Unprofessional Behavior',
                'Institutional Rules Violation',
                'Teaching Standards Failure',
                'Academic Integrity Violation',
                'Unprofessional Behavior',
                'Institutional Rules Violation',
                'Teaching Standards Failure'
            ]
        }
        
        result = classifier.train(training_data['texts'], training_data['labels'])
        print(json.dumps({
            'success': True,
            'accuracy': result['accuracy'],
            'message': 'Model trained successfully'
        }))

if __name__ == '__main__':
    main()
