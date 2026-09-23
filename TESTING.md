# OSWD Complaint System - Testing Guide

## Manual Testing Checklist

### 1. Database Setup ✓
- [ ] Import database_complete.sql
- [ ] Verify tables created
- [ ] Check default admin user exists

### 2. ML Model Setup ✓
```bash
cd c:\xampp\htdocs\Complaint\ml_model
pip install -r requirements.txt
python classifier.py train
```

### 3. User Registration & Login
- [ ] Register as Student
- [ ] Register as Program Coordinator
- [ ] Login with admin (admin/admin123)
- [ ] Test invalid credentials
- [ ] Verify session persistence

### 4. Student Functions
- [ ] Submit new complaint
- [ ] View AI classification result
- [ ] View my complaints list
- [ ] View complaint details
- [ ] Add comments
- [ ] Check dashboard statistics

### 5. Staff Functions (OSWD)
- [ ] View all complaints
- [ ] Update complaint status
- [ ] Add internal comments
- [ ] Filter complaints by status
- [ ] View complainant details

### 6. Escalation Workflow
- [ ] Department Chair escalates complaint
- [ ] Verify escalation record created
- [ ] Check status changed to "escalated"

### 7. ML Classifier Testing
```bash
# Test prediction
cd c:\xampp\htdocs\Complaint\ml_model
python classifier.py predict "Professor showed favoritism in grading"
```

Expected output:
```json
{
  "category": "Academic Integrity Violation",
  "confidence": 0.85
}
```

### 8. UI/UX Testing
- [ ] Responsive design on mobile
- [ ] All icons display correctly
- [ ] Forms validation works
- [ ] Modal dialogs function
- [ ] Alerts dismiss properly
- [ ] Navigation menu works

### 9. Security Testing
- [ ] SQL injection prevention
- [ ] XSS protection (input sanitization)
- [ ] Session hijacking prevention
- [ ] Role-based access control
- [ ] Password hashing verification

### 10. Performance Testing
- [ ] Page load time < 2 seconds
- [ ] ML classification < 1 second
- [ ] Database queries optimized
- [ ] No memory leaks

## Test Cases

### Test Case 1: Submit Complaint with AI Classification
**Steps:**
1. Login as student
2. Click "Submit New Complaint"
3. Fill form with: "Teacher harassed me verbally"
4. Submit
5. Verify AI predicts "Unprofessional Behavior"

**Expected Result:** ✓ Complaint submitted, AI classification shown

### Test Case 2: Status Update
**Steps:**
1. Login as OSWD admin
2. View complaint
3. Change status to "Under Review"
4. Check timeline updated

**Expected Result:** ✓ Status updated, timeline entry created

### Test Case 3: Escalation
**Steps:**
1. Login as Department Chair
2. View complaint
3. Click "Escalate"
4. Provide reason
5. Submit

**Expected Result:** ✓ Escalation record created, status changed

## Database Queries for Verification

```sql
-- Check users
SELECT * FROM users;

-- Check complaints
SELECT * FROM complaints;

-- Check ML predictions
SELECT complaint_id, predicted_category, status FROM complaints;

-- Check timeline
SELECT * FROM complaint_timeline ORDER BY created_at DESC;

-- Check comments
SELECT * FROM complaint_comments;
```

## Common Issues & Solutions

### Issue: ML Classifier Not Working
**Solution:**
```bash
pip install --upgrade scikit-learn numpy pandas
python classifier.py train
```

### Issue: Database Connection Error
**Solution:**
- Check MySQL is running in XAMPP
- Verify credentials in config/database.php
- Ensure database exists

### Issue: Session Not Persisting
**Solution:**
- Check session_start() in config.php
- Verify PHP session directory has write permissions

### Issue: CSS Not Loading
**Solution:**
- Clear browser cache
- Check file paths in HTML
- Verify Apache is serving static files

## Browser Compatibility
- [x] Chrome 90+
- [x] Firefox 88+
- [x] Edge 90+
- [x] Safari 14+

## Production Deployment Checklist
- [ ] Disable error display (config.php)
- [ ] Change default admin password
- [ ] Enable HTTPS
- [ ] Configure backup system
- [ ] Set up email notifications
- [ ] Configure Python path in MLClassifier.php
- [ ] Optimize database indexes
- [ ] Enable query caching

## Load Testing
```bash
# Apache Benchmark
ab -n 1000 -c 10 http://localhost/Complaint/login.php
```

## Accessibility Testing
- [ ] Screen reader compatibility
- [ ] Keyboard navigation
- [ ] Color contrast ratios
- [ ] Form labels present
- [ ] Alt text for images

---
**Last Updated:** September 21, 2026
**System Version:** 1.0
