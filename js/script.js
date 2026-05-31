
// verification code check
const correctCode = "123456";

// password match

document.getElementById("signupForm")?.addEventListener("submit",function(e){

var password=document.getElementById("password").value;
var confirm=document.getElementById("confirmPassword").value;
var verificationCode=document.getElementById("verificationCode").value;

if(password!==confirm){

alert("Passwords do not match");
e.preventDefault();

}

if(verificationCode!==correctCode){

alert("Verification code is incorrect");
e.preventDefault();

}

});


// show password signup

function togglePassword(){

var pass=document.getElementById("password");

if(pass.type==="password"){

pass.type="text";

}else{

pass.type="password";

}

}


// show password login

function toggleLoginPassword(){

var pass=document.getElementById("loginPassword");

if(pass.type==="password"){

pass.type="text";

}else{

pass.type="password";

}

}


// verification code check for login

document.getElementById("loginForm")?.addEventListener("submit",function(e){

var verificationCode=document.getElementById("verificationCode").value;

if(verificationCode!==correctCode){

alert("Verification code is incorrect");
e.preventDefault();

} else {
    e.preventDefault();
    
    // Check email to redirect
    var emailInput = document.querySelector('input[type="email"]');
    var email = emailInput ? emailInput.value.toLowerCase() : "";
    
    if(email === "admin" || email === "admin@jeem.com") {
        window.location.href = "admin_dashboard.html";
    } else {
        window.location.href = "manager_dashboard.html";
    }
}

});


// password strength

document.getElementById("password")?.addEventListener("input",function(){

var strength=document.getElementById("strength");
var value=this.value;

if(value.length<5){

strength.innerText="Weak";

}else if(value.length<8){

strength.innerText="Medium";

}else{

strength.innerText="Strong";

}

});