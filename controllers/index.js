const Surprise = require('../models/surprise');
const User = require('../models/user');
const bcrypt = require('bcrypt');
const jwt = require('jsonwebtoken');

module.exports = {
    index,
    logout,
    login,
    showLogin,
};

function showLogin(req, res) {
    res.render('login', { surprisesLeft: undefined });
}

async function index(req, res) {
    const allSurprises = await Surprise.find({});
    const completedSurprises = allSurprises.filter(s => s.viewed);
    res.render('index', {
        allSurprises,
        completedSurprises,
        surprisesLeft: 30 - completedSurprises.length,
        user: req.session.user
    });
}

async function login(req, res) {
    const user = await User.findOne({ name: req.body.name });
    if (!user) return res.redirect('/');
    const match = await bcrypt.compare(req.body.password, user.password);
    if (!match) return res.redirect('/');
    updateSessionVals(req, {
        token: createJWT(user),
        loggedIn: true,
        user
    });
    return res.redirect(`/admin`);
}

function logout(req, res) {
    req.session.destroy();
    return res.redirect('/');
}

/*-- Helper Functions --*/

function createJWT(user) {
    return jwt.sign(
        { user },
        process.env.SECRET,
        { expiresIn: process.env.JWT_MAX_AGE }
    );
}

function updateSessionVals(req, newSessionVals) {
    for (const sessionKey in newSessionVals) {
        const sessionVal = newSessionVals[sessionKey];
        if (sessionKey === 'user') {
            if (typeof sessionVal.preferences === 'object') {
                sessionVal.preferences = sessionVal.preferences._id
            }
        }
        req.session[sessionKey] = sessionVal;
    }
}