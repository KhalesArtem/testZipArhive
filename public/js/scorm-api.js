/**
 * SCORM 1.2 API Implementation
 */
window.API = {
    LMSInitialize: function(param) {
        console.log('LMSInitialize called');
        return "true";
    },
    
    LMSFinish: function(param) {
        console.log('LMSFinish called');
        return "true";
    },
    
    LMSGetValue: function(element) {
        console.log('LMSGetValue:', element);
        
        // Return default values for common SCORM elements
        switch(element) {
            case "cmi.core.student_id":
                return "1";
            case "cmi.core.student_name":
                return "Demo User";
            case "cmi.core.lesson_status":
                return "incomplete";
            case "cmi.core.lesson_mode":
                return "normal";
            case "cmi.core.credit":
                return "credit";
            case "cmi.core.entry":
                return "ab-initio";
            case "cmi.core.lesson_location":
                return "";
            case "cmi.suspend_data":
                return "";
            case "cmi.launch_data":
                return "";
            case "cmi.core.score.raw":
                return "";
            case "cmi.core.score.max":
                return "100";
            case "cmi.core.score.min":
                return "0";
            case "cmi.core.total_time":
                return "0000:00:00.00";
            case "cmi.core.session_time":
                return "0000:00:00.00";
            default:
                return "";
        }
    },
    
    LMSSetValue: function(element, value) {
        console.log('LMSSetValue:', element, '=', value);
        
        // Store values in sessionStorage for persistence
        sessionStorage.setItem('scorm_' + element, value);
        
        // Send important data to server
        if (element === 'cmi.core.lesson_status' || 
            element === 'cmi.core.score.raw' ||
            element === 'cmi.core.session_time') {
            
            // You can send this data to server via AJAX if needed
            fetch('/api/scorm/track', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || ''
                },
                body: JSON.stringify({
                    element: element,
                    value: value,
                    package_id: window.scormPackageId || 0
                })
            }).catch(err => console.error('Error tracking SCORM data:', err));
        }
        
        return "true";
    },
    
    LMSCommit: function(param) {
        console.log('LMSCommit called');
        return "true";
    },
    
    LMSGetLastError: function() {
        return "0";
    },
    
    LMSGetErrorString: function(errorCode) {
        return "No error";
    },
    
    LMSGetDiagnostic: function(errorCode) {
        return "No error";
    }
};

/**
 * SCORM 2004 API (API_1484_11)
 */
window.API_1484_11 = {
    Initialize: function(param) {
        console.log('Initialize called (SCORM 2004)');
        return "true";
    },
    
    Terminate: function(param) {
        console.log('Terminate called (SCORM 2004)');
        return "true";
    },
    
    GetValue: function(element) {
        console.log('GetValue (2004):', element);
        
        // Map SCORM 2004 elements to values
        switch(element) {
            case "cmi.learner_id":
                return "1";
            case "cmi.learner_name":
                return "Demo User";
            case "cmi.completion_status":
                return "incomplete";
            case "cmi.success_status":
                return "unknown";
            case "cmi.mode":
                return "normal";
            case "cmi.credit":
                return "credit";
            case "cmi.entry":
                return "ab-initio";
            case "cmi.location":
                return "";
            case "cmi.suspend_data":
                return "";
            case "cmi.launch_data":
                return "";
            case "cmi.score.raw":
                return "";
            case "cmi.score.max":
                return "100";
            case "cmi.score.min":
                return "0";
            case "cmi.total_time":
                return "PT0H0M0S";
            case "cmi.session_time":
                return "PT0H0M0S";
            default:
                return "";
        }
    },
    
    SetValue: function(element, value) {
        console.log('SetValue (2004):', element, '=', value);
        sessionStorage.setItem('scorm2004_' + element, value);
        return "true";
    },
    
    Commit: function(param) {
        console.log('Commit called (SCORM 2004)');
        return "true";
    },
    
    GetLastError: function() {
        return "0";
    },
    
    GetErrorString: function(errorCode) {
        return "No error";
    },
    
    GetDiagnostic: function(errorCode) {
        return "No error";
    }
};

// Also set up findAPI functions that some SCORM packages use
window.findAPI = function(win) {
    if (win.API) return win.API;
    if (win.parent && win.parent != win) {
        return findAPI(win.parent);
    }
    return null;
};

window.getAPI = function() {
    return window.API;
};

window.getAPI_1484_11 = function() {
    return window.API_1484_11;
};

console.log('SCORM API loaded');