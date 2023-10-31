const Surprise = require('../models/surprise');
const { DateTime } = require('luxon');
const nodemailer = require('nodemailer');
const emailRegex = /(?:[a-z0-9!#$%&'*+/=?^_`{|}~-]+(?:\.[a-z0-9!#$%&'*+/=?^_`{|}~-]+)*|"(?:[\x01-\x08\x0b\x0c\x0e-\x1f\x21\x23-\x5b\x5d-\x7f]|\\[\x01-\x09\x0b\x0c\x0e-\x7f])*")@(?:(?:[a-z0-9](?:[a-z0-9-]*[a-z0-9])?\.)+[a-z0-9](?:[a-z0-9-]*[a-z0-9])?|\[(?:(?:(2(5[0-5]|[0-4][0-9])|1[0-9][0-9]|[1-9]?[0-9]))\.){3}(?:(2(5[0-5]|[0-4][0-9])|1[0-9][0-9]|[1-9]?[0-9])|[a-z0-9-]*[a-z0-9]:(?:[\x01-\x08\x0b\x0c\x0e-\x1f\x21-\x5a\x53-\x7f]|\\[\x01-\x09\x0b\x0c\x0e-\x7f])+)\])/ // sourced from https://stackoverflow.com/questions/201323/how-can-i-validate-an-email-address-using-a-regular-expression

module.exports = {
    viewed,
    create,
    update,
    notify,
    all,
    testEmail,
    delete: deleteOne
};

async function all(req, res) {
    try {
        res.json(await getAllSurprises());
    } catch(error) {
        res.json({ error: error.message });
    }
}

async function getAllSurprises() {
    const surprisesWithRevealDate = await Surprise.find({ revealDate: { $ne: null } }).sort({ revealDate: 1 })
    const surprisesWithoutRevealDate = await Surprise.find({ revealDate: null });
    return [...surprisesWithRevealDate, ...surprisesWithoutRevealDate];
}

async function notify(req, res) {
    try {
        const surprises = await Surprise.find({}).sort({ revealDate: 1 });
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
        res.json({ error: error.message });
    }
}

async function viewed(req, res) {
    try {
        const surprise = await Surprise.findById(req.params.id);

        surprise.viewed = true;

        await surprise.save();
        res.json(surprise);
    } catch(error) {
        res.json({ error: error.message });
    }

}

async function deleteOne(req, res) {
    try {
        const surprise = await Surprise.findByIdAndDelete(req.params.id);
        res.json(surprise);
    } catch(error) {
        res.json({ error: error.message });
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
        res.json({ error: error.message });
    }
}

async function create(req, res) {
    try {
        const newSurprise = new Surprise(req.body);
        newSurprise.revealDate = DateTime.fromISO(req.body.revealDate, { zone: 'America/New_York' }).toJSON();
        await newSurprise.save();
        const surprises = await getAllSurprises();
        const emailRes = await sendEmail(
            "New Surprise!",
            "There's a new surprise waiting for you! See if you can guess it 😉"
        );
        res.json({ surprises, newSurprise, emailRes });
    } catch(error) {
        res.json({ error: error.message });
    }
}

async function testEmail(req, res) {
    try {
        if (process.env.TESTING_PASSWORD !== req.body.password) {
            res.json({
                error: "Invalid credentials"
            });
            return;
        }
        const emailRes = await sendEmail(
            req.body.email_subject,
            req.body.email_body,
            emailRegex.test(req.body.to_email) ? req.body.to_email : undefined
        );
        res.json({ emailRes });
    } catch(error) {
        res.json({ error: error.message });
    }
}


/******* MAIL FUNCTIONS *******/


async function sendEmail(subject, text, overrideEmail) {
    try {
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
            to: overrideEmail || process.env.TO_EMAIL,
            subject,
            text: `${text}\n\nhttps://thirty-surprises-a96594f80b00.herokuapp.com`
        };
        
        // Send email

        return await transporter.sendMail(mailOptions);
        
    } catch(err) {
        return { error: err.message };
    }
}