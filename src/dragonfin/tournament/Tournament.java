package dragonfin.tournament;

import java.io.*;
import com.fasterxml.jackson.core.*;

public class Tournament
{
	String eventName;
	String eventLocation;
	String eventBeginDate;
	String eventBeginTime;
	String eventEndDate;
	String eventEndTime;

	transient boolean dirty;

	public boolean isDirty()
	{
		return dirty;
	}

	public void loadFile(File file)
		throws IOException
	{
		FileInputStream stream = new FileInputStream(file);
		JsonParser in = new JsonFactory().createJsonParser(stream);
		parse(in);
		in.close();
	}

	public void saveFile(File file)
		throws IOException
	{
		FileOutputStream stream = new FileOutputStream(file);
		JsonGenerator out = new JsonFactory().createJsonGenerator(stream);
		write(out);
		out.close();
	}

	public void parse(JsonParser in)
		throws IOException
	{
		if (in.nextToken() != JsonToken.START_OBJECT)
			throw new JsonParseException("expected START_OBJECT",
				in.getCurrentLocation());
		while (in.nextToken() != JsonToken.END_OBJECT)
		{
			String fieldName = in.getCurrentName();
			in.nextToken();
			if (fieldName.equals("eventName")) {
				eventName = in.getText();
			}
			else if (fieldName.equals("eventLocation")) {
				eventLocation = in.getText();
			}
			else if (fieldName.equals("eventBeginDate")) {
				eventBeginDate = in.getText();
			}
			else if (fieldName.equals("eventBeginTime")) {
				eventBeginTime = in.getText();
			}
			else if (fieldName.equals("eventEndDate")) {
				eventEndDate = in.getText();
			}
			else if (fieldName.equals("eventEndTime")) {
				eventEndTime = in.getText();
			}
			else {
				in.skipChildren();
			}
		}
	}

	public void write(JsonGenerator out)
		throws IOException
	{
		out.writeStartObject();
		out.writeStringField("eventName", eventName);
		out.writeStringField("eventLocation", eventLocation);
		out.writeStringField("eventBeginDate", eventBeginDate);
		out.writeStringField("eventBeginTime", eventBeginTime);
		out.writeStringField("eventEndDate", eventEndDate);
		out.writeStringField("eventEndTime", eventEndTime);
		out.writeEndObject();
	}
}
