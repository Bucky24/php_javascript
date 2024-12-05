import { foo, bar } from "./bleh.js";

const name = "frog";
if (name === "blah") {
    console.log("foo bar");
} else if (name === "thing") {
    console.log('feh');
} else {
    console.log("bar foo");
}

for (let i=0;i<5;i++) {
    console.log(i);
}

foo();