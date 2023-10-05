const Surprise = require('../models/surprise');
const { DateTime } = require('luxon');
const nodemailer = require('nodemailer');

module.exports = {
    viewed,
    create,
    update,
    notify,
    delete: deleteOne
};


async function notify(req, res) {
    try {
        const surprises = await Surprise.find({});
        const surpriseIdx = surprises.findIndex(s => s._id == req.params.id);
        let success = true, message = "Success";
    
        if (surpriseIdx >= 0) {
            sendEmail(
                "Your Surprise is Ready!",
                `Looks like it's time to see surprise #${surpriseIdx + 1}!!!`
            );
        } else {
            success = false;
            message = `There is no surprise with the id of "${req.params.id}"`;
        }
        res.json({
            success,
            message
        });
    } catch(error) {
        res.json({
            error: error.message
        });
    }
}

async function viewed(req, res) {
    try {
        const surprise = await Surprise.findById(req.params.id);

        surprise.viewed = true;

        await surprise.save();
        res.json(surprise);
    } catch(error) {
        res.json({
            error: error.message
        });
    }

}

async function deleteOne(req, res) {
    try {
        const surprise = await Surprise.findByIdAndDelete(req.params.id);
        res.json(surprise);
    } catch(error) {
        res.json({
            error: error.message
        });
    }
}

async function update(req, res) {
    try {
        const surprise = await Surprise.findById(req.params.id);

        surprise.title = req.body.title;
        surprise.description = req.body.description;
        surprise.magnitude = req.body.magnitude;
        surprise.variety = req.body.variety;
        surprise.iconClass = req.body.iconClass;
        surprise.revealDate = req.body.revealDate;
        surprise.viewed = req.body.viewed === 'true';
        surprise.live = req.body.live === 'true';

        if (req.body.completed === 'true') {
            if (!surprise.completedAt) surprise.completedAt = new Date();
        } else {
            surprise.completedAt = undefined;
        }

        await surprise.save();
        res.json(surprise);
    } catch(error) {
        res.json({
            error: error.message
        });
    }
}

async function create(req, res) {
    try {
        const surprise = new Surprise(req.body);
        surprise.revealDate = DateTime.fromISO(req.body.revealDate, { zone: 'America/New_York' }).toJSON();
        await surprise.save();
        sendEmail(
            "New Surprise!",
            "There's a new surprise waiting for you!"
        );
        res.json(surprise);
    } catch(error) {
        res.json({
            error: error.message
        });
    }
}


/******* MAIL FUNCTIONS *******/


function sendEmail(subject, text) {
    const transporter = nodemailer.createTransport({
        service: "gmail",
        auth: {
            type: 'OAuth2',
            user: process.env.MAIL_USERNAME,
            pass: process.env.MAIL_PASSWORD,
            clientId: process.env.OAUTH_CLIENT_ID,
            clientSecret: process.env.OAUTH_CLIENT_SECRET,
            refreshToken: process.env.OAUTH_REFRESH_TOKEN,
        }
    });

    const mailOptions = {
        from: process.env.MAIL_USERNAME,
        to: process.env.TO_EMAIL,
        subject,
        text: `${text}\n\nhttps://thirty-surprises-a96594f80b00.herokuapp.com`
    };
    
    // Send email
    transporter.sendMail(mailOptions, function(error, info){
        if (error) {
            console.error(error);
        } else {
            console.log('Email sent: ' + info.response);
        }
    });
}